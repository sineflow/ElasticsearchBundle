<?php

namespace Sineflow\ElasticsearchBundle\Exception;

/**
 * Exception thrown when there are errors in the response of a bulk request
 */
class BulkRequestException extends Exception
{
    private $bulkResponseItems = [];

    private $bulkRequest = [];

    /**
     * @param string $bulkResponseItems
     * @param array  $bulkRequest
     */
    public function setBulkResponseItems($bulkResponseItems, array $bulkRequest)
    {
        $this->bulkResponseItems = $bulkResponseItems;
        $this->bulkRequest = $bulkRequest;
    }

    /**
     * @return array
     */
    public function getBulkResponseItems()
    {
        return $this->bulkResponseItems;
    }

    /**
     * @return array
     */
    public function getBulkRequest()
    {
        return $this->bulkRequest;
    }
}
