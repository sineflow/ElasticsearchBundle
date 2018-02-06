<?php

namespace Sineflow\ElasticsearchBundle\Result;

use Sineflow\ElasticsearchBundle\Document\ObjectInterface;

/**
 * ObjectIterator class.
 */
class ObjectIterator implements \Countable, \Iterator, \ArrayAccess
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
    private $rawData;

    /**
     * Keeps a reference to all returned converted objects, so the same instances can be returned if requested again
     * TODO: this may create a memory consumption issue
     *
     * @var ObjectInterface[]
     */
    private $objects = [];

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
        $this->rawData = $rawData;
    }

    /**
     * @return int
     */
    public function count()
    {
        return count($this->rawData);
    }

    /**
     * @return ObjectInterface
     */
    public function current()
    {
        if (!isset($this->objects[$this->key()])) {
            $this->objects[$this->key()] = $this->convertToObject($this->rawData[$this->key()]);
        }

        return $this->objects[$this->key()];
    }

    /**
     * {@inheritdoc}
     */
    public function next()
    {
        next($this->rawData);
    }

    /**
     * {@inheritdoc}
     */
    public function key()
    {
        return key($this->rawData);
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
        reset($this->rawData);
    }

    /**
     * @param integer $offset
     *
     * @return bool
     */
    public function offsetExists($offset)
    {
        return isset($this->rawData[$offset]);
    }

    /**
     * @param integer $offset
     *
     * @return ObjectInterface|null
     */
    public function offsetGet($offset)
    {
        if (!isset($this->objects[$offset])) {
            $this->objects[$offset] = isset($this->rawData[$offset]) ? $this->convertToObject($this->rawData[$offset]) : null;
        }

        return $this->objects[$offset];
    }

    /**
     * @param integer|null    $offset
     * @param ObjectInterface $value
     */
    public function offsetSet($offset, $value)
    {
        $rawValue = $this->documentConverter->convertToArray($value, $this->propertyMetadata['propertiesMetadata']);

        if (is_null($offset)) {
            $this->rawData[] = $rawValue;
        } else {
            $this->rawData[$offset] = $rawValue;
        }
    }

    /**
     * @param integer $offset
     */
    public function offsetUnset($offset)
    {
        unset($this->rawData[$offset]);
    }

    /**
     * Converts flat array with fields and their value (as they come from Elasticsearch) to an ObjectInterface object
     *
     * @param array $rawData
     *
     * @return ObjectInterface
     */
    private function convertToObject(array $rawData)
    {
        return $this->documentConverter->assignArrayToObject(
            $rawData,
            new $this->propertyMetadata['className'](),
            $this->propertyMetadata['propertiesMetadata']
        );
    }
}
