<?php

namespace Sineflow\ElasticsearchBundle\Exception;

/**
 * Exception thrown when an index is currently being rebuilt
 */
class IndexRebuildingException extends Exception
{
    /**
     * @var array
     */
    private $indicesInProgress;

    /**
     * @param array          $indicesInProgress The physical indices, which are in the process of being built
     * @param int            $code
     * @param Exception|null $previous
     */
    public function __construct(array $indicesInProgress, $code = 0, Exception $previous = null)
    {
        parent::__construct(sprintf('Index is currently being rebuilt as "%s"', implode(', ', $indicesInProgress)), $code, $previous);

        $this->indicesInProgress = $indicesInProgress;
    }

    /**
     * @return array
     */
    public function getIndices()
    {
        return $this->indicesInProgress;
    }
}
