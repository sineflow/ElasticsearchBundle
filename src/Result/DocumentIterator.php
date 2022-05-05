<?php

namespace Sineflow\ElasticsearchBundle\Result;

use Sineflow\ElasticsearchBundle\Document\DocumentInterface;
use Sineflow\ElasticsearchBundle\DTO\IndicesToDocumentClasses;

/**
 * This class is able to iterate over Elasticsearch result documents while casting data into objects
 */
class DocumentIterator implements \Countable, \Iterator
{
    /**
     * @var array
     */
    private $rawData;

    /**
     * @var DocumentConverter
     */
    private $documentConverter;

    /**
     * @var IndicesToDocumentClasses
     */
    private $indicesToDocumentClasses;

    /**
     * @var array
     */
    private $suggestions = [];

    /**
     * @var array
     */
    private $aggregations = [];

    /**
     * @var array
     */
    private $documents = [];

    /**
     * Constructor.
     *
     * @param array $rawData
     */
    public function __construct($rawData, DocumentConverter $documentConverter, IndicesToDocumentClasses $indicesToDocumentClasses)
    {
        $this->rawData = $rawData;
        $this->documentConverter = $documentConverter;
        $this->indicesToDocumentClasses = $indicesToDocumentClasses;

        if (isset($rawData['suggest'])) {
            $this->suggestions = &$rawData['suggest'];
        }
        if (isset($rawData['aggregations'])) {
            $this->aggregations = &$rawData['aggregations'];
        }
        if (isset($rawData['hits']['hits'])) {
            $this->documents = &$rawData['hits']['hits'];
        }
    }

    /**
     * @return array
     */
    public function getSuggestions()
    {
        return $this->suggestions;
    }

    /**
     * @return array
     */
    public function getAggregations()
    {
        return $this->aggregations;
    }

    /**
     * Returns total count of records matching the query.
     *
     * @return int
     */
    public function getTotalCount()
    {
        return $this->rawData['hits']['total']['value'];
    }

    /**
     * @return int
     */
    public function count()
    {
        return \count($this->documents);
    }

    /**
     * @return DocumentInterface
     */
    public function current()
    {
        return isset($this->documents[$this->key()]) ? $this->convertToDocument($this->documents[$this->key()]) : null;
    }

    /**
     * {@inheritdoc}
     */
    public function next()
    {
        \next($this->documents);
    }

    /**
     * {@inheritdoc}
     */
    public function key()
    {
        return \key($this->documents);
    }

    /**
     * {@inheritdoc}
     */
    public function valid()
    {
        return null !== $this->key();
    }

    /**
     * {@inheritdoc}
     */
    public function rewind()
    {
        \reset($this->documents);
    }

    /**
     * Converts raw array to document.
     *
     * @param array $rawData
     *
     * @return DocumentInterface
     *
     * @throws \LogicException
     */
    private function convertToDocument($rawData)
    {
        $documentClass = $this->indicesToDocumentClasses->get($rawData['_index']);

        return $this->documentConverter->convertToDocument($rawData, $documentClass);
    }
}
