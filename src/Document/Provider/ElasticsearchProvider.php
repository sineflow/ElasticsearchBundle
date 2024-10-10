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
     * Specify how long a consistent view of the index should be maintained for a scrolled search
     */
    protected string $scrollTime = '5m';

    /**
     * Number of documents in one chunk sent to ES
     */
    protected int $chunkSize = 500;

    /**
     * @param DocumentMetadataCollector $metadataCollector   The metadata collector
     * @param string|IndexManager       $sourceIndexManager  The index manager of the data source
     * @param string                    $sourceDocumentClass The document class the data is coming from
     */
    public function __construct(
        protected DocumentMetadataCollector $metadataCollector,
        protected string|IndexManager $sourceIndexManager,
        protected string $sourceDocumentClass,
    ) {
    }

    /**
     * Returns a PHP Generator for iterating over the full dataset of source data that is to be inserted in ES
     *
     * @return \Generator<array>
     */
    public function getDocuments(): \Generator
    {
        $repo = $this->sourceIndexManager->getRepository();

        /** @var ScrollAdapter $scrollAdapter */
        $scrollAdapter = $repo->find(
            ['sort' => ['_doc']],
            Finder::RESULTS_RAW | Finder::ADAPTER_SCROLL,
            [
                'index'  => $this->sourceIndexManager->getLiveIndex(),
                'size'   => $this->chunkSize,
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
     */
    public function getDocument(int|string $id): array
    {
        $params = [
            'index' => $this->sourceIndexManager->getLiveIndex(),
            'id'    => $id,
        ];
        $doc = $this->sourceIndexManager->getConnection()->getClient()->get($params);
        $result = $doc['_source'];
        $result['_id'] = $doc['_id'];

        return $result;
    }
}
