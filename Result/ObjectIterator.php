<?php

namespace Sineflow\ElasticsearchBundle\Result;

/**
 * ObjectIterator class.
 */
class ObjectIterator implements \Countable, \Iterator
{
    /**
     * @var array property metadata information.
     */
    private $propertyMetadata;

    /**
     * @var DocumentConverter
     */
    private $documentConverter;

    /**
     * @var array
     */
    private $objects;

    /**
     * Constructor.
     *
     * @param DocumentConverter $documentConverter
     * @param array             $rawData
     * @param array             $propertyMetadata
     */
    public function __construct(DocumentConverter $documentConverter, array $rawData, array $propertyMetadata)
    {
        $this->documentConverter = $documentConverter;
        $this->propertyMetadata = $propertyMetadata;
        $this->objects = $rawData;
    }

    /**
     * @return int
     */
    public function count()
    {
        return count($this->objects);
    }

    /**
     * @return DocumentInterface
     */
    public function current()
    {
        return $this->convertToObject($this->objects[$this->key()]);
    }

    /**
     * {@inheritdoc}
     */
    public function next()
    {
        next($this->objects);
    }

    /**
     * {@inheritdoc}
     */
    public function key()
    {
        return key($this->objects);
    }

    /**
     * {@inheritdoc}
     */
    public function valid()
    {
        return $this->key() !== null;
    }

    /**
     * {@inheritdoc}
     */
    public function rewind()
    {
        reset($this->objects);
    }

    /**
     * {@inheritdoc}
     */
    private function convertToObject($rawData)
    {
        return $this->documentConverter->assignArrayToObject(
            $rawData,
            new $this->propertyMetadata['className'](),
            $this->propertyMetadata['propertiesMetadata']
        );
    }
}
