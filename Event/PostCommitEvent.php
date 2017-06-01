<?php

namespace Sineflow\ElasticsearchBundle\Event;

use Symfony\Component\EventDispatcher\Event;

/**
 * Class PostCommitEvent
 */
class PostCommitEvent extends Event
{
    /**
     * @var array
     */
    private $bulkResponse;

    /**
     * @param array $bulkResponse
     */
    public function __construct(array $bulkResponse)
    {
        $this->bulkResponse = $bulkResponse;
    }

    /**
     * @return array
     */
    public function getBulkResponse()
    {
        return $this->bulkResponse;
    }
}
