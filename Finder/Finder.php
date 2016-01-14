<?php

namespace Sineflow\ElasticsearchBundle\Finder;

use Elasticsearch\Common\Exceptions\Missing404Exception;
use Sineflow\ElasticsearchBundle\DTO\TypesToDocumentClasses;
use Sineflow\ElasticsearchBundle\Manager\ConnectionManager;
use Sineflow\ElasticsearchBundle\Manager\IndexManagerRegistry;
use Sineflow\ElasticsearchBundle\Mapping\DocumentMetadataCollector;
use Sineflow\ElasticsearchBundle\Paginator\KnpPaginatorAdapter;
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

    const BITMASK_RESULT_ADAPTERS = 64;
    const ADAPTER_KNP = 64;

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
     * @param string $documentClass In short notation (i.e AppBundle:Product)
     * @param string $id
     * @param int    $resultType
     * @return mixed
     */
    public function get($documentClass, $id, $resultType = self::RESULTS_OBJECT)
    {
        $client = $this->getConnection([$documentClass])->getClient();

        $allDocumentClassToIndexMappings = $this->documentMetadataCollector->getDocumentClassesIndices();
        $indexManagerName = $allDocumentClassToIndexMappings[$documentClass];
        $documentMetadata = $this->documentMetadataCollector->getDocumentMetadata($documentClass);

        $params = [
            'index' => $this->indexManagerRegistry->get($indexManagerName)->getReadAlias(),
            'type' => $documentMetadata->getType(),
            'id' => $id,
        ];

        try {
            $raw = $client->get($params);
        } catch (Missing404Exception $e) {
            return null;
        }

        switch ($resultType & self::BITMASK_RESULT_TYPES) {
            case self::RESULTS_OBJECT:
                return $this->documentConverter->convertToDocument($raw, $documentClass);
            case self::RESULTS_ARRAY:
                return $this->convertToNormalizedArray($raw);
            case self::RESULTS_RAW:
                return $raw;
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
     * @param int      $totalHits               The total hits of the query response
     * @return mixed
     */
    public function find(array $documentClasses, array $searchBody, $resultsType = self::RESULTS_OBJECT, array $additionalRequestParams = [], &$totalHits = null)
    {
        if (($resultsType & self::BITMASK_RESULT_ADAPTERS) === self::ADAPTER_KNP) {
            return new KnpPaginatorAdapter($this, $documentClasses, $searchBody, $resultsType, $additionalRequestParams);
        }

        $client = $this->getConnection($documentClasses)->getClient();

        $indicesAndTypes = $this->getTargetIndicesAndTypes($documentClasses);

        $params = array_merge($indicesAndTypes, [
            'body' => $searchBody,
        ]);

        if (!empty($additionalRequestParams)) {
            $params = array_merge($additionalRequestParams, $params);
        }

        $raw = $client->search($params);

        $totalHits = $raw['hits']['total'];

        return $this->parseResult($raw, $resultsType, $documentClasses);
    }

    /**
     * Returns the number of records matching the given query
     *
     * @param array $documentClasses
     * @param array $searchBody
     * @param array $additionalRequestParams
     * @return int
     */
    public function count(array $documentClasses, array $searchBody, array $additionalRequestParams = [])
    {
        $client = $this->getConnection($documentClasses)->getClient();

        $indicesAndTypes = $this->getTargetIndicesAndTypes($documentClasses);

        $params = array_merge($indicesAndTypes, [
            'body' => $searchBody,
        ]);

        if (!empty($additionalRequestParams)) {
            $params = array_merge($additionalRequestParams, $params);
        }

        $raw = $client->count($params);

        return $raw['count'];

    }

    /**
     * Returns an array with the Elasticsearch indices and types to be queried,
     * based on the given document classes in short notation (AppBundle:Product)
     *
     * @param string[] $documentClasses
     * @return array
     */
    public function getTargetIndicesAndTypes(array $documentClasses)
    {
        $allDocumentClassToIndexMappings = $this->documentMetadataCollector->getDocumentClassesIndices();
        $documentClassToIndexMap = array_intersect_key($allDocumentClassToIndexMappings, array_flip($documentClasses));

        $indices = [];
        $types = [];
        foreach ($documentClassToIndexMap as $documentClass => $indexManagerName) {
            $documentMetadata = $this->documentMetadataCollector->getDocumentMetadata($documentClass);

            $indices[] = $this->indexManagerRegistry->get($indexManagerName)->getReadAlias();
            $types[] = $documentMetadata->getType();
        }

        $result = [
            'index' => array_unique($indices),
            'type' => $types,
        ];

        return $result;
    }

    /**
     * Returns a mapping of live indices and types to the document classes in short notation that represent them
     *
     * @param string[] $documentClasses
     * @return TypesToDocumentClasses
     */
    private function getTypesToDocumentClasses(array $documentClasses)
    {
        $typesToDocumentClasses = new TypesToDocumentClasses();

        $documentClassToIndexMap = $this->documentMetadataCollector->getDocumentClassesIndices($documentClasses);
        $documentClassToTypeMap = $this->documentMetadataCollector->getDocumentClassesTypes($documentClasses);

        $getLiveIndices = false;
        // If there are duplicate type names across the indices we're querying
        if (count($documentClassToTypeMap) > count(array_unique($documentClassToTypeMap))) {
            // We'll need to get the live index name for each type, so we can correctly map the results to the appropriate objects
            $getLiveIndices = true;
        }

        foreach ($documentClassToIndexMap as $documentClass => $indexManagerName) {
            // Build mappings of indices and types to document class names, for the Converter
            $liveIndex = $getLiveIndices ? $this->indexManagerRegistry->get($indexManagerName)->getLiveIndex() : null;
            $typesToDocumentClasses->set($liveIndex, $documentClassToTypeMap[$documentClass], $documentClass);
        }

        return $typesToDocumentClasses;
    }


    private function parseResult($raw, $resultsType, array $documentClasses = null)
    {
        switch ($resultsType & self::BITMASK_RESULT_TYPES) {
            case self::RESULTS_OBJECT:
                if (empty($documentClasses)) {
                    throw new \InvalidArgumentException('$documentClasses must be specified when retrieving results as objects');
                }

                return new DocumentIterator(
                    $raw,
                    $this->documentConverter,
                    $this->getTypesToDocumentClasses($documentClasses)
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
     * Normalizes response array.
     *
     * @param array $data
     *
     * @return array
     */
    private function convertToNormalizedArray($data)
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
        }

        return $output;
    }

    /**
     * Verify that all types are in indices using the same connection object and return that object
     *
     * @param array $documentClasses
     * @return ConnectionManager
     */
    private function getConnection(array $documentClasses)
    {
        $connection = null;
        foreach ($documentClasses as $documentClass) {
            $indexManagerName = $this->documentMetadataCollector->getDocumentClassIndex($documentClass);
            $indexManager = $this->indexManagerRegistry->get($indexManagerName);
            if (!is_null($connection) && $indexManager->getConnection()->getConnectionName() != $connection->getConnectionName()) {
                throw new \InvalidArgumentException(sprintf('All searched types must be in indices within the same connection'));
            }
            $connection = $indexManager->getConnection();
        }

        return $connection;
    }
}
