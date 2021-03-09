<?php

namespace Sineflow\ElasticsearchBundle\Finder\Adapter;

use Sineflow\ElasticsearchBundle\Exception\Exception;
use Sineflow\ElasticsearchBundle\Finder\Finder;

/**
 * Class ScrollAdapter
 */
class ScrollAdapter
{
    /**
     * @var Finder
     */
    private $finder;

    /**
     * @var array
     */
    private $documentClasses;

    /**
     * @var string
     */
    private $scrollId;

    /**
     * @var string
     */
    private $scrollTime;

    /**
     * @var int
     */
    private $resultsType;

    /**
     * @var int
     */
    private $totalHits = null;

    /**
     * When a search query with a 'scroll' param is performed, not only the scroll id is returned, but also the
     * initial batch of results, so we'll cache those here to be returned on the first call to getNextScrollResults()
     *
     * @var array
     */
    private $initialResults;

    /**
     * @param Finder $finder
     * @param array  $documentClasses
     * @param array  $rawResults      The raw results from the initial search call
     * @param int    $resultsType
     * @param string $scrollTime      The value for the 'scroll' param in a scroll request
     */
    public function __construct(Finder $finder, array $documentClasses, $rawResults, $resultsType, $scrollTime)
    {
        $this->finder = $finder;
        $this->documentClasses = $documentClasses;
        $this->scrollId = $rawResults['_scroll_id'];
        $this->scrollTime = $scrollTime;
        $this->initialResults = $rawResults;
        // Make sure we don't get an adapter returned again when we recursively execute the paginated find()
        $this->resultsType = $resultsType & ~ Finder::BITMASK_RESULT_ADAPTERS;
    }

    /**
     * Returns results from a scroll request
     *
     * @return mixed
     */
    public function getNextScrollResults()
    {
        // If this is the first call to this method, return the cached initial results from the search request
        if (null !== $this->initialResults) {
            if (count($this->initialResults['hits']['hits']) > 0) {
                $results = $this->finder->parseResult($this->initialResults, $this->resultsType, $this->documentClasses);
            } else {
                $results = false;
            }
            $this->initialResults = null;
        } else {
            // Execute a scroll request
            $results = $this->finder->scroll(
                $this->documentClasses,
                $this->scrollId,
                $this->scrollTime,
                $this->resultsType,
                $this->totalHits
            );
        }

        return $results;
    }

    /**
     * Returns the total hits by the query, which the scroll is for
     * or throws an exception, if no scrolls have been retrieved yet
     *
     * @return int
     *
     * @throws Exception
     */
    public function getTotalHits(): int
    {
        if (is_null($this->totalHits)) {
            throw new Exception(sprintf('You must call getNextScrollResults() at least once, before you can get the total hits'));
        }

        return $this->totalHits;
    }
}
