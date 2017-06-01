<?php

namespace Sineflow\ElasticsearchBundle\Event;

use Sineflow\ElasticsearchBundle\Document\DocumentInterface;
use Symfony\Component\EventDispatcher\Event;

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
     * @var int
     */
    private $bulkOperationIndex;

    /**
     * @param DocumentInterface $document
     * @param int               $bulkOperationIndex
     */
    public function __construct(DocumentInterface $document, $bulkOperationIndex)
    {
        $this->document           = $document;
        $this->bulkOperationIndex = $bulkOperationIndex;
    }

    /**
     * @return DocumentInterface
     */
    public function getDocument()
    {
        return $this->document;
    }

    /**
     * @return int
     */
    public function getBulkOperationIndex()
    {
        return $this->bulkOperationIndex;
    }
}
