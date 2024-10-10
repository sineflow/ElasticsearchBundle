<?php

namespace Sineflow\ElasticsearchBundle\Event;

use Sineflow\ElasticsearchBundle\Manager\ConnectionManager;
use Symfony\Contracts\EventDispatcher\Event;

/**
 * Class PostCommitEvent
 */
class PostCommitEvent extends Event
{
    private readonly string $connectionName;

    public function __construct(private readonly array $bulkResponse, ConnectionManager $connectionManager)
    {
        $this->connectionName = $connectionManager->getConnectionName();
    }

    public function getBulkResponse(): array
    {
        return $this->bulkResponse;
    }

    public function getConnectionName(): string
    {
        return $this->connectionName;
    }
}
