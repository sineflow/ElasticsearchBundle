<?php

namespace Sineflow\ElasticsearchBundle\Result;

use Sineflow\ElasticsearchBundle\Document\DocumentInterface;
use Sineflow\ElasticsearchBundle\DTO\IndicesToDocumentClasses;

/**
 * This class is able to iterate over Elasticsearch result documents while casting data into objects
 */
class DocumentIterator implements \Countable, \Iterator
{
    private array $suggestions = [];
    private array $aggregations = [];
    private array $documents = [];

    public function __construct(
        private array $rawData,
        private readonly DocumentConverter $documentConverter,
        private readonly IndicesToDocumentClasses $indicesToDocumentClasses,
    ) {
        if (isset($this->rawData['suggest'])) {
            $this->suggestions = &$this->rawData['suggest'];
        }
        if (isset($this->rawData['aggregations'])) {
            $this->aggregations = &$this->rawData['aggregations'];
        }
        if (isset($this->rawData['hits']['hits'])) {
            $this->documents = &$this->rawData['hits']['hits'];
        }
    }

    public function getSuggestions(): array
    {
        return $this->suggestions;
    }

    public function getAggregations(): array
    {
        return $this->aggregations;
    }

    /**
     * Returns total count of records matching the query.
     */
    public function getTotalCount(): int
    {
        return $this->rawData['hits']['total']['value'];
    }

    public function count(): int
    {
        return \count($this->documents);
    }

    public function current(): ?DocumentInterface
    {
        return isset($this->documents[$this->key()]) ? $this->convertToDocument($this->documents[$this->key()]) : null;
    }

    /**
     * {@inheritdoc}
     */
    public function next(): void
    {
        \next($this->documents);
    }

    /**
     * {@inheritdoc}
     */
    public function key(): ?int
    {
        return \key($this->documents);
    }

    /**
     * {@inheritdoc}
     */
    public function valid(): bool
    {
        return null !== $this->key();
    }

    /**
     * {@inheritdoc}
     */
    public function rewind(): void
    {
        \reset($this->documents);
    }

    /**
     * Converts raw array to document.
     *
     * @throws \LogicException
     */
    private function convertToDocument(array $rawData): DocumentInterface
    {
        $documentClass = $this->indicesToDocumentClasses->get($rawData['_index']);

        return $this->documentConverter->convertToDocument($rawData, $documentClass);
    }
}
