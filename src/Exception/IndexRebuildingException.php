<?php

namespace Sineflow\ElasticsearchBundle\Exception;

/**
 * Exception thrown when an index is currently being rebuilt
 */
class IndexRebuildingException extends \RuntimeException implements ElasticsearchBundleException
{
    private readonly array $indicesInProgress;

    /**
     * @param array $indicesInProgress The physical indices, which are in the process of being built
     */
    public function __construct(array $indicesInProgress, int $code = 0, ?\Throwable $previous = null)
    {
        parent::__construct(\sprintf('Index is currently being rebuilt as "%s"', \implode(', ', $indicesInProgress)), $code, $previous);

        $this->indicesInProgress = $indicesInProgress;
    }

    public function getIndices(): array
    {
        return $this->indicesInProgress;
    }
}
