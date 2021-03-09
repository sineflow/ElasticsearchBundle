<?php

namespace Sineflow\ElasticsearchBundle\Finder;

use Sineflow\ElasticsearchBundle\DTO\IndicesToDocumentClasses;
use Sineflow\ElasticsearchBundle\Finder\Adapter\ScrollAdapter;
use Sineflow\ElasticsearchBundle\Manager\ConnectionManager;
use Sineflow\ElasticsearchBundle\Manager\IndexManagerRegistry;
use Sineflow\ElasticsearchBundle\Mapping\DocumentMetadataCollector;
use Sineflow\ElasticsearchBundle\Finder\Adapter\KnpPaginatorAdapter;
use Sineflow\ElasticsearchBundle\Result\DocumentConverter;
use Sineflow\ElasticsearchBundle\Result\DocumentIterator;

/**
 * Finder class for searching in ES indexes
 */
class Finder
{
    const BITMASK_RESULT_TYPES = 63;
    const RESULTS_ARRAY = 1;
    const RESULTS_OBJECT = 2;
    const RESULTS_RAW = 4;

    const BITMASK_RESULT_ADAPTERS = 192;
    const ADAPTER_KNP = 64;
    const ADAPTER_SCROLL = 128;

    const SCROLL_TIME = '1m';

    /**
     * @var DocumentMetadataCollector
     */
    private $documentMetadataCollector;

    /**
     * @var IndexManagerRegistry
     */
    private $indexManagerRegistry;

    /**
     * @var DocumentConverter
     */
    private $documentConverter;

    /**
     * Finder constructor.
     * @param DocumentMetadataCollector $documentMetadataCollector
     * @param IndexManagerRegistry      $indexManagerRegistry
     * @param DocumentConverter         $documentConverter
     */
    public function __construct(
        DocumentMetadataCollector $documentMetadataCollector,
        IndexManagerRegistry $indexManagerRegistry,
        DocumentConverter $documentConverter
    ) {
        $this->documentMetadataCollector = $documentMetadataCollector;
        $this->indexManagerRegistry = $indexManagerRegistry;
        $this->documentConverter = $documentConverter;
    }

    /**
     * Returns a document by identifier
     *
     * @param string $documentClass FQN or short notation (i.e App:Product)
     * @param string $id
     * @param int    $resultType
     *
     * @return mixed
     */
    public function get(string $documentClass, string $id, int $resultType = self::RESULTS_OBJECT)
    {
        $indexManagerName = current($this->documentMetadataCollector->getIndexManagersForDocumentClasses([$documentClass]));

        $search = [
            'index' => $this->indexManagerRegistry->get($indexManagerName)->getReadAlias(),
            'body' => ['query' => ['ids' => ['values' => [$id]]], 'version' => true],
        ];
        $results = $this->getConnection([$documentClass])->getClient()->search($search);

        // The document id must not be duplicated across the indices pointed by the read alias,
        // but in case it is, just return the first one we get
        $rawDoc = $results['hits']['hits'][0] ?? null;

        if (null === $rawDoc) {
            return null;
        }

        switch ($resultType & self::BITMASK_RESULT_TYPES) {
            case self::RESULTS_OBJECT:
                return $this->documentConverter->convertToDocument($rawDoc, $documentClass);
            case self::RESULTS_ARRAY:
                return $this->convertToNormalizedArray($rawDoc);
            case self::RESULTS_RAW:
                return $rawDoc;
            default:
                throw new \InvalidArgumentException('Wrong result type selected');
        }
    }

    /**
     * Executes a search and return results
     *
     * @param string[] $documentClasses         The ES entities to search in
     * @param array    $searchBody              The body of the search request
     * @param int      $resultsType             Bitmask value determining how the results are returned
     * @param array    $additionalRequestParams Additional params to pass to the ES client's search() method
     * @param int      $totalHits               (out param) The total hits of the query response
     *
     * @return mixed
     */
    public function find(array $documentClasses, array $searchBody, $resultsType = self::RESULTS_OBJECT, array $additionalRequestParams = [], &$totalHits = null)
    {
        if (($resultsType & self::BITMASK_RESULT_ADAPTERS) === self::ADAPTER_KNP) {
            return new KnpPaginatorAdapter($this, $documentClasses, $searchBody, $resultsType, $additionalRequestParams);
        }

        $client = $this->getConnection($documentClasses)->getClient();

        $params = ['index' => $this->getTargetIndices($documentClasses)];

        // Add any additional params specified, overwriting the current ones
        // This allows for overriding the target index if necessary
        if (!empty($additionalRequestParams)) {
            $params = array_replace_recursive($params, $additionalRequestParams);
        }

        // Set the body here, as we don't want to allow overriding it with the $additionalRequestParams
        $params['body'] = $searchBody;

        // Execute a scroll request
        if (($resultsType & self::BITMASK_RESULT_ADAPTERS) === self::ADAPTER_SCROLL) {
            // Set default scroll and size, unless custom ones were provided through $additionalRequestParams
            $params = array_replace_recursive([
                'scroll' => self::SCROLL_TIME,
                'body' => ['sort' => ['_doc']],
            ], $params);

            $rawResults = $client->search($params);

            return new ScrollAdapter($this, $documentClasses, $rawResults, $resultsType, $params['scroll']);
        }

        $raw = $client->search($params);

        $totalHits = $raw['hits']['total']['value'];

        return $this->parseResult($raw, $resultsType, $documentClasses);
    }

    /**
     * Executes a scroll request, based on a given scrollId.
     * Returns false when there are no more hits
     *
     * @param array    $documentClasses The ES entities involved in the scrolled search
     * @param string   $scrollId        (in/out param) The Scroll ID as returned from the Scan request or a previous Scroll request
     * @param string   $scrollTime      The time to keep the scroll window open
     * @param int      $resultsType     Bitmask value determining how the results are returned
     * @param int|null $totalHits       (out param) The total hits of the query response
     *
     * @return mixed
     */
    public function scroll(array $documentClasses, string &$scrollId, string $scrollTime = self::SCROLL_TIME, int $resultsType = self::RESULTS_OBJECT, ?int &$totalHits = null)
    {
        $client = $this->getConnection($documentClasses)->getClient();

        $params = [
            'body' => [
                'scroll_id' => $scrollId,
                'scroll' => $scrollTime,
            ],
        ];

        $raw = $client->scroll($params);

        $scrollId = $raw['_scroll_id'];

        $totalHits = $raw['hits']['total']['value'];

        return (count($raw['hits']['hits']) > 0) ? $this->parseResult($raw, $resultsType, $documentClasses) : false;
    }

    /**
     * Returns the number of records matching the given query
     *
     * @param array $documentClasses
     * @param array $searchBody
     * @param array $additionalRequestParams
     *
     * @return int
     */
    public function count(array $documentClasses, array $searchBody = [], array $additionalRequestParams = []): int
    {
        $client = $this->getConnection($documentClasses)->getClient();

        $params = ['index' => $this->getTargetIndices($documentClasses)];

        if (!empty($searchBody)) {
            // Make sure sorting is not set in the query as it is not allowed for a count request
            // ES2 didn't mind, but ES5 with throw an exception
            unset($searchBody['sort']);

            $params['body'] = $searchBody;
        }

        if (!empty($additionalRequestParams)) {
            $params = array_merge($additionalRequestParams, $params);
        }

        $raw = $client->count($params);

        return $raw['count'];
    }

    /**
     * Returns an array with the Elasticsearch indices to be queried,
     * based on the given document classes in short notation (App:Product) or FQN
     *
     * @param array $documentClasses
     *
     * @return array
     */
    public function getTargetIndices(array $documentClasses) : array
    {
        $indexManagersForDocumentClasses = $this->documentMetadataCollector->getIndexManagersForDocumentClasses($documentClasses);

        $indices = [];
        foreach ($indexManagersForDocumentClasses as $documentClass => $indexManagerName) {
            $indices[] = $this->indexManagerRegistry->get($indexManagerName)->getReadAlias();
        }

        return $indices;
    }

    /**
     * Parse raw search result into an object iterator, array or as-is, depending on results type
     *
     * @param array    $raw             The raw results as received from Elasticsearch
     * @param int      $resultsType     Bitmask value determining how the results are returned
     * @param string[] $documentClasses The ES entity classes that may be returned from the search
     *
     * @return array|DocumentIterator
     */
    public function parseResult(array $raw, int $resultsType, array $documentClasses = null)
    {
        switch ($resultsType & self::BITMASK_RESULT_TYPES) {
            case self::RESULTS_OBJECT:
                if (empty($documentClasses)) {
                    throw new \InvalidArgumentException('$documentClasses must be specified when retrieving results as objects');
                }

                return new DocumentIterator(
                    $raw,
                    $this->documentConverter,
                    $this->getIndicesToDocumentClasses($documentClasses)
                );

            case self::RESULTS_ARRAY:
                return $this->convertToNormalizedArray($raw);

            case self::RESULTS_RAW:
                return $raw;

            default:
                throw new \InvalidArgumentException('Wrong results type selected');
        }
    }

    /**
     * Returns a mapping of live indices to the document classes that represent them
     *
     * @param string[] $documentClasses
     *
     * @return IndicesToDocumentClasses
     */
    private function getIndicesToDocumentClasses(array $documentClasses): IndicesToDocumentClasses
    {
        $indicesToDocumentClasses = new IndicesToDocumentClasses();
        $documentClassToIndexManagerMap = $this->documentMetadataCollector->getIndexManagersForDocumentClasses($documentClasses);

        $getLiveIndices = (count($documentClasses) > 1);

        foreach ($documentClassToIndexManagerMap as $documentClass => $indexManagerName) {
            // Build mappings of indices to document class names, for the Converter
            if (!$getLiveIndices) {
                $indicesToDocumentClasses->set(null, $documentClass);
            } else {
                $readIndices = $this->indexManagerRegistry->get($indexManagerName)->getReadIndices();
                foreach ($readIndices as $readIndex) {
                    $indicesToDocumentClasses->set($readIndex, $documentClass);
                }
            }
        }

        return $indicesToDocumentClasses;
    }

    /**
     * Normalizes response array.
     *
     * @param array $data
     *
     * @return array
     */
    private function convertToNormalizedArray(array $data): array
    {
        if (array_key_exists('_source', $data)) {
            return $data['_source'];
        }

        $output = [];

        if (isset($data['hits']['hits'][0]['_source'])) {
            foreach ($data['hits']['hits'] as $item) {
                $output[$item['_id']] = $item['_source'];
            }
        } elseif (isset($data['hits']['hits'][0]['fields'])) {
            foreach ($data['hits']['hits'] as $item) {
                $output[$item['_id']] = array_map('reset', $item['fields']);
            }
        } else {
            // If empty fields param was supplied (meaning no fields are returned)
            foreach ($data['hits']['hits'] as $item) {
                $output[$item['_id']] = null;
            }
        }

        return $output;
    }

    /**
     * Verify that all types are in indices using the same connection object and return that object
     *
     * @param array $documentClasses
     *
     * @return ConnectionManager|null
     */
    private function getConnection(array $documentClasses): ?ConnectionManager
    {
        $connection = null;
        foreach ($documentClasses as $documentClass) {
            $indexManagerName = $this->documentMetadataCollector->getDocumentClassIndex($documentClass);
            $indexManager = $this->indexManagerRegistry->get($indexManagerName);
            if (!is_null($connection) && $indexManager->getConnection()->getConnectionName() !== $connection->getConnectionName()) {
                throw new \InvalidArgumentException(sprintf('All searched types must be in indices within the same connection'));
            }
            $connection = $indexManager->getConnection();
        }

        return $connection;
    }
}
