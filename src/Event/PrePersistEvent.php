<?php

namespace Sineflow\ElasticsearchBundle\Event;

use Sineflow\ElasticsearchBundle\Document\DocumentInterface;
use Sineflow\ElasticsearchBundle\Manager\ConnectionManager;
use Symfony\Contracts\EventDispatcher\Event;

class PrePersistEvent extends Event
{
    private readonly string $connectionName;
    private readonly int $bulkOperationIndex;

    public function __construct(private readonly DocumentInterface $document, ConnectionManager $connectionManager)
    {
        $this->connectionName = $connectionManager->getConnectionName();
        $this->bulkOperationIndex = $connectionManager->getBulkOperationsCount();
    }

    public function getDocument(): DocumentInterface
    {
        return $this->document;
    }

    public function getConnectionName(): string
    {
        return $this->connectionName;
    }

    public function getBulkOperationIndex(): int
    {
        return $this->bulkOperationIndex;
    }
}
