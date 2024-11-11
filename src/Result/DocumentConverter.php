<?php

namespace Sineflow\ElasticsearchBundle\Result;

use Psr\Cache\InvalidArgumentException;
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
     * Constructor.
     */
    public function __construct(
        protected DocumentMetadataCollector $metadataCollector,
        protected string $languageSeparator,
    ) {
    }

    /**
     * Converts raw array (as returned by the Elasticsearch client) to document.
     *
     * @param string $documentClass Document class FQN or in short notation (e.g. App:Product)
     *
     * @throws InvalidArgumentException
     */
    public function convertToDocument(array $rawData, string $documentClass): DocumentInterface
    {
        // Get document metadata
        $metadata = $this->metadataCollector->getDocumentMetadata($documentClass);

        switch (true) {
            case isset($rawData['_source']):
                $data = $rawData['_source'];
                break;

            case isset($rawData['fields']):
                $data = \array_map('current', $rawData['fields']);
                /* Check for returned fields as well (@see https://www.elastic.co/guide/en/elasticsearch/reference/7.11/search-fields.html#docvalue-fields) */
                // TODO: when partial fields of nested objects are selected, partial objects should be constructed
                foreach ($data as $key => $field) {
                    if (\is_array($field)) {
                        $data = \array_merge($data, $field);
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

        $className = $metadata->getClassName();
        /** @var DocumentInterface $document */
        $document = $this->assignArrayToObject($data, new $className(), $metadata->getPropertiesMetadata());

        return $document;
    }

    /**
     * Assigns all properties to object.
     *
     * @param array           $array  Flat array with fields and their value
     * @param ObjectInterface $object A document or a (nested) object
     */
    public function assignArrayToObject(array $array, ObjectInterface $object, array $propertiesMetadata): ObjectInterface
    {
        foreach ($propertiesMetadata as $esField => $propertyMetadata) {
            // Skip fields from the mapping that have no value set, unless they are multilanguage fields
            if (empty($propertyMetadata['multilanguage']) && !isset($array[$esField])) {
                continue;
            }

            if (!empty($propertyMetadata['enumType'])) {
                $objectValue = $propertyMetadata['enumType']::from($array[$esField]);
            } elseif (\in_array($propertyMetadata['type'], ['string', 'keyword', 'text']) && !empty($propertyMetadata['multilanguage'])) {
                $objectValue = null;
                foreach ($array as $fieldName => $value) {
                    $prefixLength = \strlen($esField.$this->languageSeparator);
                    if (\substr($fieldName, 0, $prefixLength) === $esField.$this->languageSeparator) {
                        if (!$objectValue) {
                            $objectValue = new MLProperty();
                        }
                        $language = \substr($fieldName, $prefixLength);
                        $objectValue->setValue($value, $language);
                    }
                }
            } elseif (\in_array($propertyMetadata['type'], ['object', 'nested'])) {
                // ES doesn't mind having either single or multiple objects with the same mapping, but in this bundle we must specifically declare either.
                // So we must make sure everything works for a 'multiple' definition where we actually have a single object and vice versa.
                if ($propertyMetadata['multiple'] && \is_string(\key($array[$esField]))) {
                    // field is declared multiple, but actual data is single object
                    $data = [$array[$esField]];
                } elseif (!$propertyMetadata['multiple'] && 0 === \key($array[$esField])) {
                    // field is declared as single object, but actual data is an array of objects
                    if (\count($array[$esField]) > 1) {
                        throw new DocumentConversionException(\sprintf('Multiple objects found for a single object field `%s`', $propertyMetadata['propertyName']));
                    }
                    $data = \current($array[$esField]);
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

            if (DocumentMetadata::PROPERTY_ACCESS_PRIVATE === $propertyMetadata['propertyAccess']) {
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
     * @param ObjectInterface $object A document or a (nested) object
     *
     * @throws \ReflectionException|InvalidArgumentException
     */
    public function convertToArray(ObjectInterface $object, array $propertiesMetadata = []): array
    {
        if (empty($propertiesMetadata)) {
            $propertiesMetadata = $this->metadataCollector->getObjectPropertiesMetadata($object::class);
        }

        $array = [];

        foreach ($propertiesMetadata as $name => $propertyMetadata) {
            if (DocumentMetadata::PROPERTY_ACCESS_PRIVATE === $propertyMetadata['propertyAccess']) {
                $value = $object->{$propertyMetadata['methods']['getter']}();
            } else {
                $value = $object->{$propertyMetadata['propertyName']};
            }

            if (isset($value)) {
                // If this is a (nested) object or a list of such
                if (\array_key_exists('propertiesMetadata', $propertyMetadata)) {
                    $new = [];
                    if ($propertyMetadata['multiple']) {
                        // Verify value is traversable
                        if (!(\is_array($value) || (\is_object($value) && $value instanceof \Traversable))) {
                            throw new \InvalidArgumentException(\sprintf('Value of "%s" is not traversable, although field is set to "multiple"', $propertyMetadata['propertyName']));
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
     * Check if object is the correct class
     *
     * @throws \InvalidArgumentException
     */
    private function checkObjectType(ObjectInterface $object, string $expectedClass): void
    {
        if ($object::class !== $expectedClass) {
            throw new \InvalidArgumentException(\sprintf('Expected object of "%s", got "%s"', $expectedClass, $object::class));
        }
    }
}
