<?php

namespace Sineflow\ElasticsearchBundle\Document\Provider;

use Sineflow\ElasticsearchBundle\Document\DocumentInterface;

/**
 * Defines the interface that data providers must implement
 * Data providers are classes providing input data for populating an Elasticsearch index
 */
interface ProviderInterface
{
    /**
     * Returns a PHP Generator for iterating over the full dataset of source data that is to be inserted in ES
     * The returned data can be either a document entity or an array ready for direct sending to ES
     *
     * @return \Generator<DocumentInterface|array>
     */
    public function getDocuments(): \Generator;

    /**
     * Build and return a document entity from the data source
     * The returned data can be either a document entity or an array ready for direct sending to ES
     */
    public function getDocument(int|string $id): DocumentInterface|array|null;

    /**
     * Returns the number of Elasticsearch documents to persist in a single bulk request
     * If null is returned, the 'bulk_batch_size' of the Connection will be used
     */
    public function getPersistRequestBatchSize(): ?int;
}
