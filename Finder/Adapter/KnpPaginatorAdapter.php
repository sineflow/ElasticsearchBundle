<?php

namespace Sineflow\ElasticsearchBundle\Finder\Adapter;

use Sineflow\ElasticsearchBundle\Finder\Finder;

/**
 * Class KnpPaginatorAdapter
 */
class KnpPaginatorAdapter
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
     * @var array
     */
    private $searchBody;

    /**
     * @var int
     */
    private $resultsType;

    /**
     * @var array
     */
    private $additionalRequestParams;

    /**
     * @var int
     */
    private $totalHits;

    /**
     * @param Finder $finder
     * @param array  $documentClasses
     * @param array  $searchBody
     * @param int    $resultsType
     * @param array  $additionalRequestParams
     */
    public function __construct(Finder $finder, array $documentClasses, array $searchBody, $resultsType, array $additionalRequestParams = [])
    {
        $this->finder = $finder;
        $this->documentClasses = $documentClasses;
        $this->searchBody = $searchBody;
        // Make sure we don't get an adapter returned again when we recursively execute the paginated find()
        $this->resultsType = $resultsType & ~ Finder::BITMASK_RESULT_ADAPTERS;
        $this->additionalRequestParams = $additionalRequestParams;
    }

    /**
     * @return int
     */
    public function getResultsType()
    {
        return $this->resultsType;
    }

    /**
     * Return results for this page only
     *
     * @param int    $offset
     * @param int    $count
     * @param string $sortField
     * @param string $sortDir
     * @return mixed
     */
    public function getResults($offset, $count, $sortField = null, $sortDir = 'asc')
    {
        $searchBody = $this->searchBody;
        $searchBody['from'] = $offset;
        $searchBody['size'] = $count;

        if ($sortField) {
            if (!isset($searchBody['sort'])) {
                $searchBody['sort'] = [];
            }
            // If sorting is set in the request in advance and the main sort field is the same as the one set for KNP, remove it
            if (isset($searchBody['sort'][0]) && key($searchBody['sort'][0]) === $sortField) {
                array_shift($searchBody['sort']);
            }
            // Keep any preliminary set order as a secondary order to the query
            array_unshift($searchBody['sort'], [$sortField => ['order' => $sortDir]]);
        }

        return $this->finder->find($this->documentClasses, $searchBody, $this->resultsType, $this->additionalRequestParams, $this->totalHits);
    }

    /**
     * Return the total hits from the executed getResults()
     *
     * @return int
     */
    public function getTotalHits()
    {
        return $this->totalHits;
    }
}
