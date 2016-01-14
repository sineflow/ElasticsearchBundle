<?php

namespace Sineflow\ElasticsearchBundle\Exception;

/**
 * Exception thrown when there are errors in the response of a bulk request
 */
class BulkRequestException extends Exception
{
    private $bulkResponseItems = [];

    /**
     * @param string $bulkResponseItems
     */
    public function setBulkResponseItems($bulkResponseItems)
    {
        $this->bulkResponseItems = $bulkResponseItems;
    }

    /**
     * @return array
     */
    public function getBulkResponseItems()
    {
        return $this->bulkResponseItems;
    }
}
