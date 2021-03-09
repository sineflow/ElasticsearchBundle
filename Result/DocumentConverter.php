<?php

namespace Sineflow\ElasticsearchBundle\Result;

use Sineflow\ElasticsearchBundle\Document\DocumentInterface;
use Sineflow\ElasticsearchBundle\Document\MLProperty;
use Sineflow\ElasticsearchBundle\Document\ObjectInterface;
use Sineflow\ElasticsearchBundle\Exception\DocumentConversionException;
use Sineflow\ElasticsearchBundle\Mapping\DocumentMetadata;
use Sineflow\ElasticsearchBundle\Mapping\DocumentMetadataCollector;

/**
 * Converter from array to document object and vice versa
 */
class DocumentConverter
{
    /**
     * @var DocumentMetadataCollector
     */
    protected $metadataCollector;

    /**
     * @var string
     */
    protected $languageSeparator;

    /**
     * Constructor.
     *
     * @param DocumentMetadataCollector $metadataCollector
     * @param string                    $languageSeparator
     */
    public function __construct(DocumentMetadataCollector $metadataCollector, $languageSeparator)
    {
        $this->metadataCollector = $metadataCollector;
        $this->languageSeparator = $languageSeparator;
    }


    /**
     * Converts raw array (as returned by the Elasticsearch client) to document.
     *
     * @param array  $rawData
     * @param string $documentClass Document class FQN or in short notation (e.g. App:Product)
     *
     * @return DocumentInterface
     */
    public function convertToDocument($rawData, $documentClass)
    {
        // Get document metadata
        $metadata = $this->metadataCollector->getDocumentMetadata($documentClass);

        switch (true) {
            case isset($rawData['_source']):
                $data = $rawData['_source'];
                break;

            case isset($rawData['fields']):
                $data = array_map('reset', $rawData['fields']);
                /** Check for partial fields as well (@see https://www.elastic.co/guide/en/elasticsearch/reference/1.4/search-request-fields.html) */
                // TODO: when partial fields of nested objects are selected, partial objects should be constructed
                foreach ($data as $key => $field) {
                    if (is_array($field)) {
                        $data = array_merge($data, $field);
                        unset($data[$key]);
                    }
                }
                break;

            default:
                $data = [];
        }

        // Add special fields to data
        foreach (['_id', '_score'] as $specialField) {
            if (isset($rawData[$specialField])) {
                $data[$specialField] = $rawData[$specialField];
            }
        }

        /** @var DocumentInterface $document */
        $className = $metadata->getClassName();
        $document = $this->assignArrayToObject($data, new $className(), $metadata->getPropertiesMetadata());

        return $document;
    }

    /**
     * Assigns all properties to object.
     *
     * @param array           $array              Flat array with fields and their value
     * @param ObjectInterface $object             A document or a (nested) object
     * @param array           $propertiesMetadata
     *
     * @return ObjectInterface
     */
    public function assignArrayToObject(array $array, ObjectInterface $object, array $propertiesMetadata)
    {
        foreach ($propertiesMetadata as $esField => $propertyMetadata) {
            // Skip fields from the mapping that have no value set, unless they are multilanguage fields
            if (empty($propertyMetadata['multilanguage']) && !isset($array[$esField])) {
                continue;
            }

            if (in_array($propertyMetadata['type'], ['string', 'keyword', 'text']) && !empty($propertyMetadata['multilanguage'])) {
                $objectValue = null;
                foreach ($array as $fieldName => $value) {
                    $prefixLength = strlen($esField.$this->languageSeparator);
                    if (substr($fieldName, 0, $prefixLength) === $esField.$this->languageSeparator) {
                        if (!$objectValue) {
                            $objectValue = new MLProperty();
                        }
                        $language = substr($fieldName, $prefixLength);
                        $objectValue->setValue($value, $language);
                    }
                }
            } elseif (in_array($propertyMetadata['type'], ['object', 'nested'])) {
                // ES doesn't mind having either single or multiple objects with the same mapping, but in this bundle we must specifically declare either.
                // So we must make sure everything works for a 'multiple' definition where we actually have a single object and vice versa.
                if ($propertyMetadata['multiple'] && is_string(key($array[$esField]))) {
                    // field is declared multiple, but actual data is single object
                    $data = [$array[$esField]];
                } elseif (!$propertyMetadata['multiple'] && key($array[$esField]) === 0) {
                    // field is declared as single object, but actual data is an array of objects
                    if (count($array[$esField]) > 1) {
                        throw new DocumentConversionException(sprintf('Multiple objects found for a single object field `%s`', $propertyMetadata['propertyName']));
                    }
                    $data = current($array[$esField]);
                } else {
                    $data = $array[$esField];
                }

                if ($propertyMetadata['multiple']) {
                    $objectValue = new ObjectIterator($this, $data, $propertyMetadata);
                } else {
                    $objectValue = $this->assignArrayToObject(
                        $data,
                        new $propertyMetadata['className'](),
                        $propertyMetadata['propertiesMetadata']
                    );
                }
            } else {
                $objectValue = $array[$esField];
            }

            if ($propertyMetadata['propertyAccess'] === DocumentMetadata::PROPERTY_ACCESS_PRIVATE) {
                $object->{$propertyMetadata['methods']['setter']}($objectValue);
            } else {
                $object->{$propertyMetadata['propertyName']} = $objectValue;
            }
        }

        return $object;
    }

    /**
     * Converts document or (nested) object to an array.
     *
     * @param ObjectInterface $object             A document or a (nested) object
     * @param array           $propertiesMetadata
     *
     * @return array
     */
    public function convertToArray(ObjectInterface $object, $propertiesMetadata = [])
    {
        if (empty($propertiesMetadata)) {
            $propertiesMetadata = $this->metadataCollector->getObjectPropertiesMetadata(get_class($object));
        }

        $array = [];

        foreach ($propertiesMetadata as $name => $propertyMetadata) {
            if ($propertyMetadata['propertyAccess'] === DocumentMetadata::PROPERTY_ACCESS_PRIVATE) {
                $value = $object->{$propertyMetadata['methods']['getter']}();
            } else {
                $value = $object->{$propertyMetadata['propertyName']};
            }

            if (isset($value)) {
                // If this is a (nested) object or a list of such
                if (array_key_exists('propertiesMetadata', $propertyMetadata)) {
                    $new = [];
                    if ($propertyMetadata['multiple']) {
                        // Verify value is traversable
                        if (!(is_array($value) || (is_object($value) && $value instanceof \Traversable))) {
                            throw new \InvalidArgumentException(sprintf('Value of "%s" is not traversable, although field is set to "multiple"', $propertyMetadata['propertyName']));
                        }

                        foreach ($value as $item) {
                            $this->checkObjectType($item, $propertyMetadata['className']);
                            $new[] = $this->convertToArray($item, $propertyMetadata['propertiesMetadata']);
                        }
                    } else {
                        $this->checkObjectType($value, $propertyMetadata['className']);
                        $new = $this->convertToArray($value, $propertyMetadata['propertiesMetadata']);
                    }
                    $array[$name] = $new;
                } elseif ($value instanceof MLProperty) {
                    foreach ($value->getValues() as $language => $langValue) {
                        $array[$name.$this->languageSeparator.$language] = $langValue;
                    }
                } else {
                    $array[$name] = $value;
                }
            }
        }

        return $array;
    }

    /**
     * Check if object is the correct type
     *
     * @param ObjectInterface $object
     * @param array           $expectedClass
     *
     * @throws \InvalidArgumentException
     */
    private function checkObjectType(ObjectInterface $object, $expectedClass)
    {
        if (get_class($object) !== $expectedClass) {
            throw new \InvalidArgumentException(
                sprintf('Expected object of type "%s", got "%s"', $expectedClass, get_class($object))
            );
        }
    }
}
