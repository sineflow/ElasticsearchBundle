<?php

namespace Sineflow\ElasticsearchBundle\Event;

use Sineflow\ElasticsearchBundle\Manager\ConnectionManager;
use Symfony\Contracts\EventDispatcher\Event;

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
     * @var string
     */
    private $connectionName;

    public function __construct(array $bulkResponse, ConnectionManager $connectionManager)
    {
        $this->bulkResponse = $bulkResponse;
        $this->connectionName = $connectionManager->getConnectionName();
    }

    /**
     * @return array
     */
    public function getBulkResponse()
    {
        return $this->bulkResponse;
    }

    /**
     * @return string
     */
    public function getConnectionName()
    {
        return $this->connectionName;
    }
}
