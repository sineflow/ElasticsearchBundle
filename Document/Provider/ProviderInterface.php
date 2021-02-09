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
     * Returns the document class the provider is for
     *
     * @return string
     */
    public function getDocumentClass() : string;

    /**
     * Returns a PHP Generator for iterating over the full dataset of source data that is to be inserted in ES
     * The returned data can be either a document entity or an array ready for direct sending to ES
     *
     * @return \Generator<DocumentInterface|array>
     */
    public function getDocuments();

    /**
     * Build and return a document entity from the data source
     * The returned data can be either a document entity or an array ready for direct sending to ES
     *
     * @param int|string $id
     *
     * @return DocumentInterface|array
     */
    public function getDocument($id);

    /**
     * Returns the number of Elasticsearch documents to persist in a single bulk request
     * If null is returned, the 'bulk_batch_size' of the Connection will be used
     *
     * @return int|null
     */
    public function getPersistRequestBatchSize() : ?int;
}
