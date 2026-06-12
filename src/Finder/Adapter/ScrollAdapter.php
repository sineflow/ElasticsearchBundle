<?php

namespace Sineflow\ElasticsearchBundle\Finder\Adapter;

use Elastic\Elasticsearch\Exception\ClientResponseException;
use Elastic\Elasticsearch\Exception\ServerResponseException;
use Sineflow\ElasticsearchBundle\Finder\Finder;
use Sineflow\ElasticsearchBundle\Result\DocumentIterator;

class ScrollAdapter
{
    private string $scrollId;
    private readonly int $resultsType;
    private readonly int $totalHits;

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
        $this->totalHits = $rawResults['hits']['total']['value'];
        // Make sure we don't get an adapter returned again when we recursively execute the paginated find()
        $this->resultsType = $resultsType & ~Finder::BITMASK_RESULT_ADAPTERS;
    }

    /**
     * Returns the next batch of results from a scroll request, or false when there are no more.
     * Once false is returned, the scroll context is cleared and this method must not be called again -
     * doing so would attempt a scroll request with a scroll id that no longer exists on the cluster
     * and result in a ClientResponseException, unless the search matched no documents at all.
     *
     * @throws ClientResponseException
     * @throws ServerResponseException
     */
    public function getNextScrollResults(): array|DocumentIterator|false
    {
        // The search matched no documents, so no scroll context is held on the cluster -
        // Finder::find() has already cleared it
        if (0 === $this->totalHits) {
            return false;
        }

        // If this is the first call to this method, return the cached initial results from the search request
        if (null !== $this->initialResults) {
            $results = $this->finder->parseResult($this->initialResults, $this->resultsType, $this->documentClasses);
            $this->initialResults = null;
        } else {
            // Execute a scroll request, which also clears the scroll context when there are no more results
            $results = $this->finder->scroll(
                $this->documentClasses,
                $this->scrollId,
                $this->scrollTime,
                $this->resultsType
            );
        }

        return $results;
    }

    /**
     * Returns the total hits matched by the query, which the scroll is for
     */
    public function getTotalHits(): int
    {
        return $this->totalHits;
    }
}
