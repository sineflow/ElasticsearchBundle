<?php

namespace Sineflow\ElasticsearchBundle\Document\Provider;

use Sineflow\ElasticsearchBundle\Manager\IndexManager;
use Sineflow\ElasticsearchBundle\Mapping\DocumentMetadataCollector;

/**
 * Class providing data from an Elasticsearch index source
 */
class ElasticsearchProvider extends AbstractProvider
{
    /**
     * @var DocumentMetadataCollector
     */
    protected $metadataCollector;

    /**
     * @var string The index manager of the data source
     */
    protected $sourceIndexManager;

    /**
     * @var string The type the data is coming from
     */
    protected $sourceDocumentClass;

    /**
     * @var string Specify how long a consistent view of the index should be maintained for a scrolled search
     */
    protected $scrollTime = '5m';

    /**
     * @var int Number of documents in one chunk sent to ES
     */
    protected $chunkSize = 500;

    /**
     * @param string                    $documentClass       The type the provider is for
     * @param DocumentMetadataCollector $metadataCollector   The metadata collector
     * @param IndexManager              $sourceIndexManager  The index manager of the data source
     * @param string                    $sourceDocumentClass The type the data is coming from
     */
    public function __construct($documentClass, DocumentMetadataCollector $metadataCollector, IndexManager $sourceIndexManager, $sourceDocumentClass)
    {
        parent::__construct($documentClass);
        $this->sourceIndexManager = $sourceIndexManager;
        $this->metadataCollector = $metadataCollector;
        $this->sourceDocumentClass = $sourceDocumentClass;
    }

    /**
     * Returns a PHP Generator for iterating over the full dataset of source data that is to be inserted in ES
     *
     * @return \Generator<array>
     */
    public function getDocuments()
    {
        // Build a scan search request
        $params = [
            'scroll' => $this->scrollTime,
            'size' => $this->chunkSize,
            'index' => $this->sourceIndexManager->getLiveIndex(),
            'type' => $this->metadataCollector->getDocumentMetadata($this->sourceDocumentClass)->getType(),
            'body' => ['sort' => ['_doc']],
        ];

        // Get the scroll ID
        $docs = $this->sourceIndexManager->getConnection()->getClient()->search($params);
        $scrollId = $docs['_scroll_id'];

        // Loop while there are results
        while (\true) {
            // Execute a scroll request
            $response = $this->sourceIndexManager->getConnection()->getClient()->scroll(
                [
                    'scroll_id' => $scrollId,
                    'scroll' => $this->scrollTime,
                ]
            );
            if (count($response['hits']['hits']) > 0) {
                foreach ($response['hits']['hits'] as $hit) {
                    $doc = $hit['_source'];
                    $doc['_id'] = $hit['_id'];
                    yield $doc;
                }

                // Get the new scroll_id
                $scrollId = $response['_scroll_id'];
            } else {
                // No more data
                break;
            }
        }
    }

    /**
     * Build and return a document from the data source, ready for insertion into ES
     *
     * @param int|string $id
     * @return array
     */
    public function getDocument($id)
    {
        $params = [
            'index' => $this->sourceIndexManager->getLiveIndex(),
            'type' => $this->metadataCollector->getDocumentMetadata($this->sourceDocumentClass)->getType(),
            'id' => $id,
        ];
        $doc = $this->sourceIndexManager->getConnection()->getClient()->get($params);
        $result = $doc['_source'];
        $result['_id'] = $doc['_id'];

        return $result;
    }
}
