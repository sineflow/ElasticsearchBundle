<?php

namespace Sineflow\ElasticsearchBundle\Finder\Adapter;

use Sineflow\ElasticsearchBundle\Exception\Exception;
use Sineflow\ElasticsearchBundle\Finder\Finder;

/**
 * Class ScanScrollAdapter
 */
class ScanScrollAdapter
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
     * @var int
     */
    private $scrollId;

    /**
     * @var int
     */
    private $resultsType;

    /**
     * @var int
     */
    private $totalHits = null;

    /**
     * @param Finder $finder
     * @param array  $documentClasses
     * @param int    $scrollId
     * @param int    $resultsType
     */
    public function __construct(Finder $finder, array $documentClasses, $scrollId, $resultsType)
    {
        $this->finder = $finder;
        $this->documentClasses = $documentClasses;
        $this->scrollId = $scrollId;
        // Make sure we don't get an adapter returned again when we recursively execute the paginated find()
        $this->resultsType = $resultsType & ~ Finder::BITMASK_RESULT_ADAPTERS;
    }

    /**
     * Returns results from a scroll request
     * @return mixed
     */
    public function getNextScrollResults()
    {
        // Execute a scroll request
        $results = $this->finder->scroll($this->documentClasses, $this->scrollId, Finder::SCROLL_TIME, $this->resultsType, $this->totalHits);

        return $results;
    }

    /**
     * Returns the total hits by the query, which the scroll is for
     * or throws an exception, if no scrolls have been retrieved yet
     *
     * @return int
     * @throws Exception
     */
    public function getTotalHits()
    {
        if (is_null($this->totalHits)) {
            throw new Exception(sprintf('You must call getNextScrollResults() at least once, before you can get the total hits'));
        }

        return $this->totalHits;
    }
}
