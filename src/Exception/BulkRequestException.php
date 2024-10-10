<?php

namespace Sineflow\ElasticsearchBundle\Exception;

/**
 * Exception thrown when there are errors in the response of a bulk request
 */
class BulkRequestException extends Exception
{
    private array $bulkResponseItems = [];
    private array $bulkRequest = [];

    public function setBulkResponseItems(array $bulkResponseItems, array $bulkRequest): void
    {
        $this->bulkResponseItems = $bulkResponseItems;
        $this->bulkRequest = $bulkRequest;
    }

    public function getBulkResponseItems(): array
    {
        return $this->bulkResponseItems;
    }

    public function getBulkRequest(): array
    {
        return $this->bulkRequest;
    }
}
