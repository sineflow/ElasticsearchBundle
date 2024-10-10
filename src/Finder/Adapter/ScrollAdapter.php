<?php

namespace Sineflow\ElasticsearchBundle\Finder\Adapter;

use Sineflow\ElasticsearchBundle\Exception\Exception;
use Sineflow\ElasticsearchBundle\Finder\Finder;
use Sineflow\ElasticsearchBundle\Result\DocumentIterator;

/**
 * Class ScrollAdapter
 */
class ScrollAdapter
{
    private string $scrollId;
    private readonly int $resultsType;
    private ?int $totalHits = null;

    /**
     * When a search query with a 'scroll' param is performed, not only the scroll id is returned, but also the
     * initial batch of results, so we'll cache those here to be returned on the first call to getNextScrollResults()
     */
    private ?array $initialResults;

    /**
     * @param array  $rawResults The raw results from the initial search call
     * @param string $scrollTime The value for the 'scroll' param in a scroll request
     */
    public function __construct(
        private readonly Finder $finder,
        private readonly array $documentClasses,
        array $rawResults,
        int $resultsType,
        private readonly string $scrollTime,
    ) {
        $this->scrollId = $rawResults['_scroll_id'];
        $this->initialResults = $rawResults;
        // Make sure we don't get an adapter returned again when we recursively execute the paginated find()
        $this->resultsType = $resultsType & ~Finder::BITMASK_RESULT_ADAPTERS;
    }

    /**
     * Returns results from a scroll request
     */
    public function getNextScrollResults(): array|DocumentIterator|false
    {
        // If this is the first call to this method, return the cached initial results from the search request
        if (null !== $this->initialResults) {
            if (\count($this->initialResults['hits']['hits']) > 0) {
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
     * @throws Exception
     */
    public function getTotalHits(): int
    {
        if (null === $this->totalHits) {
            throw new Exception('You must call getNextScrollResults() at least once, before you can get the total hits');
        }

        return $this->totalHits;
    }
}
