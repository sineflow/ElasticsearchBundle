<?php

namespace Sineflow\ElasticsearchBundle\Document\Repository;

use Sineflow\ElasticsearchBundle\Finder\Finder;
use Sineflow\ElasticsearchBundle\Manager\IndexManager;

/**
 * Base entity repository class.
 */
class Repository
{
    /**
     * @var IndexManager
     */
    private $indexManager;

    /**
     * The document class FQN or in short notation (e.g. App:Product)
     *
     * @var string
     */
    protected $documentClass;

    /**
     * @var Finder
     */
    protected $finder;

    /**
     * Constructor.
     *
     * @param IndexManager $indexManager
     * @param Finder       $finder
     */
    public function __construct(IndexManager $indexManager, Finder $finder)
    {
        $this->indexManager = $indexManager;
        $this->finder = $finder;
        $this->documentClass = $indexManager->getDocumentClass();
    }

    /**
     * @return IndexManager
     */
    public function getIndexManager(): IndexManager
    {
        return $this->indexManager;
    }

    /**
     * Returns a single document data by ID or null if document is not found.
     *
     * @param string $id         Document Id to find.
     * @param int    $resultType Result type returned.
     *
     * @return mixed
     */
    public function getById(string $id, int $resultType = Finder::RESULTS_OBJECT)
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
     *
     * @return mixed
     */
    public function find(array $searchBody, int $resultsType = Finder::RESULTS_OBJECT, array $additionalRequestParams = [], int &$totalHits = null)
    {
        return $this->finder->find([$this->documentClass], $searchBody, $resultsType, $additionalRequestParams, $totalHits);
    }

    /**
     * Returns the number of records matching the given query
     *
     * @param array $searchBody
     * @param array $additionalRequestParams
     *
     * @return int
     */
    public function count(array $searchBody = [], array $additionalRequestParams = []): int
    {
        return $this->finder->count([$this->documentClass], $searchBody, $additionalRequestParams);
    }
}
