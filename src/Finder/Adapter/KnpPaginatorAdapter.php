<?php

namespace Sineflow\ElasticsearchBundle\Finder\Adapter;

use Sineflow\ElasticsearchBundle\Finder\Finder;
use Sineflow\ElasticsearchBundle\Result\DocumentIterator;

class KnpPaginatorAdapter
{
    private readonly int $resultsType;
    private int $totalHits = 0;

    public function __construct(
        private readonly Finder $finder,
        private readonly array $documentClasses,
        private readonly array $searchBody,
        int $resultsType,
        private readonly array $additionalRequestParams = [],
    ) {
        // Make sure we don't get an adapter returned again when we recursively execute the paginated find()
        $this->resultsType = $resultsType & ~Finder::BITMASK_RESULT_ADAPTERS;
    }

    public function getResultsType(): int
    {
        return $this->resultsType;
    }

    /**
     * Return results for this page only
     */
    public function getResults(int $offset, int $count, ?string $sortField = null, string $sortDir = 'asc'): array|DocumentIterator
    {
        $searchBody = $this->searchBody;
        $searchBody['from'] = $offset;
        $searchBody['size'] = $count;

        if ($sortField) {
            if (!isset($searchBody['sort'])) {
                $searchBody['sort'] = [];
            }
            // If sorting is set in the request in advance and the main sort field is the same as the one set for KNP, remove it
            if (isset($searchBody['sort'][0]) && \key($searchBody['sort'][0]) === $sortField) {
                \array_shift($searchBody['sort']);
            }
            // Keep any preliminary set order as a secondary order to the query
            \array_unshift($searchBody['sort'], [$sortField => ['order' => $sortDir]]);
        }

        return $this->finder->find($this->documentClasses, $searchBody, $this->resultsType, $this->additionalRequestParams, $this->totalHits);
    }

    /**
     * Return the total hits from the executed getResults()
     */
    public function getTotalHits(): int
    {
        return $this->totalHits;
    }
}
