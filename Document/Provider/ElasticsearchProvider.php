<?php

namespace Sineflow\ElasticsearchBundle\Document\Provider;

use Sineflow\ElasticsearchBundle\Finder\Adapter\ScrollAdapter;
use Sineflow\ElasticsearchBundle\Finder\Finder;
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
        $repo = $this->sourceIndexManager->getRepository();

        /** @var ScrollAdapter $scrollAdapter */
        $scrollAdapter = $repo->find(
            ['sort' => ['_doc']],
            Finder::RESULTS_RAW | Finder::ADAPTER_SCROLL,
            [
                'index' => $this->sourceIndexManager->getLiveIndex(),
                'size' => $this->chunkSize,
                'scroll' => $this->scrollTime,
            ]
        );

        while (false !== ($matches = $scrollAdapter->getNextScrollResults())) {
            foreach ($matches['hits']['hits'] as $hit) {
                $doc = $hit['_source'];
                $doc['_id'] = $hit['_id'];
                yield $doc;
            }
        }
    }

    /**
     * Build and return a document from the data source, ready for insertion into ES
     *
     * @param int|string $id
     *
     * @return array
     */
    public function getDocument($id)
    {
        $params = [
            'index' => $this->sourceIndexManager->getLiveIndex(),
            'id' => $id,
        ];
        $doc = $this->sourceIndexManager->getConnection()->getClient()->get($params);
        $result = $doc['_source'];
        $result['_id'] = $doc['_id'];

        return $result;
    }
}
