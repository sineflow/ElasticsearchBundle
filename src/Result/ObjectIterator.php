<?php

namespace Sineflow\ElasticsearchBundle\Result;

use Sineflow\ElasticsearchBundle\Document\ObjectInterface;

/**
 * ObjectIterator class.
 */
class ObjectIterator implements \Countable, \Iterator
{
    public function __construct(
        private readonly DocumentConverter $documentConverter,
        private array $objects,
        private readonly array $propertyMetadata,
    ) {
    }

    public function count(): int
    {
        return \count($this->objects);
    }

    public function current(): ?ObjectInterface
    {
        return isset($this->objects[$this->key()]) ? $this->convertToObject($this->objects[$this->key()]) : null;
    }

    /**
     * {@inheritdoc}
     */
    public function next(): void
    {
        \next($this->objects);
    }

    /**
     * {@inheritdoc}
     */
    public function key(): int|string|null
    {
        return \key($this->objects);
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
        \reset($this->objects);
    }

    private function convertToObject($rawData): ObjectInterface
    {
        return $this->documentConverter->assignArrayToObject(
            $rawData,
            new $this->propertyMetadata['className'](),
            $this->propertyMetadata['propertiesMetadata']
        );
    }
}
