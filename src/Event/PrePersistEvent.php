<?php

namespace Sineflow\ElasticsearchBundle\Event;

use Sineflow\ElasticsearchBundle\Document\DocumentInterface;
use Sineflow\ElasticsearchBundle\Manager\ConnectionManager;
use Symfony\Contracts\EventDispatcher\Event;

/**
 * Class PrePersistEvent
 */
class PrePersistEvent extends Event
{
    /**
     * @var DocumentInterface
     */
    private $document;

    /**
     * @var string
     */
    private $connectionName;

    /**
     * @var int
     */
    private $bulkOperationIndex;

    /**
     * @param DocumentInterface $document
     * @param ConnectionManager $connectionManager
     */
    public function __construct(DocumentInterface $document, ConnectionManager $connectionManager)
    {
        $this->document = $document;
        $this->connectionName = $connectionManager->getConnectionName();
        $this->bulkOperationIndex = $connectionManager->getBulkOperationsCount();
    }

    /**
     * @return DocumentInterface
     */
    public function getDocument()
    {
        return $this->document;
    }

    /**
     * @return string
     */
    public function getConnectionName()
    {
        return $this->connectionName;
    }

    /**
     * @return int
     */
    public function getBulkOperationIndex()
    {
        return $this->bulkOperationIndex;
    }
}
