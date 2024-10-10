<?php

namespace Sineflow\ElasticsearchBundle\Document\Repository;

use Psr\Cache\InvalidArgumentException;
use Sineflow\ElasticsearchBundle\Document\DocumentInterface;
use Sineflow\ElasticsearchBundle\Finder\Adapter\KnpPaginatorAdapter;
use Sineflow\ElasticsearchBundle\Finder\Adapter\ScrollAdapter;
use Sineflow\ElasticsearchBundle\Finder\Finder;
use Sineflow\ElasticsearchBundle\Manager\IndexManager;
use Sineflow\ElasticsearchBundle\Result\DocumentIterator;

/**
 * Base entity repository class.
 */
class Repository
{
    /**
     * The document class FQN or in short notation (e.g. App:Product)
     */
    protected string $documentClass;

    /**
     * @throws InvalidArgumentException
     */
    public function __construct(private readonly IndexManager $indexManager, protected Finder $finder)
    {
        $this->documentClass = $this->indexManager->getDocumentClass();
    }

    public function getIndexManager(): IndexManager
    {
        return $this->indexManager;
    }

    /**
     * Returns a single document data by ID or null if document is not found.
     *
     * @throws InvalidArgumentException
     */
    public function getById(string|int $id, int $resultType = Finder::RESULTS_OBJECT): DocumentInterface|array|null
    {
        return $this->finder->get($this->documentClass, $id, $resultType);
    }

    /**
     * Executes a search and return results
     *
     * @param array    $searchBody              The body of the search request
     * @param int      $resultsType             Bitmask value determining how the results are returned
     * @param array    $additionalRequestParams Additional params to pass to the ES client's search() method
     * @param int|null $totalHits               The total hits of the query response
     */
    public function find(array $searchBody, int $resultsType = Finder::RESULTS_OBJECT, array $additionalRequestParams = [], ?int &$totalHits = null): array|KnpPaginatorAdapter|ScrollAdapter|DocumentIterator
    {
        return $this->finder->find([$this->documentClass], $searchBody, $resultsType, $additionalRequestParams, $totalHits);
    }

    /**
     * Returns the number of records matching the given query
     */
    public function count(array $searchBody = [], array $additionalRequestParams = []): int
    {
        return $this->finder->count([$this->documentClass], $searchBody, $additionalRequestParams);
    }
}
