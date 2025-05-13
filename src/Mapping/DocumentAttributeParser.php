<?php

namespace Sineflow\ElasticsearchBundle\Mapping;

use Sineflow\ElasticsearchBundle\Attribute\DocObject;
use Sineflow\ElasticsearchBundle\Attribute\Document;
use Sineflow\ElasticsearchBundle\Attribute\Id;
use Sineflow\ElasticsearchBundle\Attribute\Property;
use Sineflow\ElasticsearchBundle\Attribute\PropertyAttributeInterface;
use Sineflow\ElasticsearchBundle\Attribute\Score;
use Sineflow\ElasticsearchBundle\Exception\InvalidMappingException;

/**
 * Document parser used for parsing and getting the metadata of a Document entity from its attributes.
 */
class DocumentAttributeParser
{
    /**
     * Contains gathered objects within the document.
     */
    private array $objects = [];

    /**
     * Document properties metadata.
     */
    private array $propertiesMetadata = [];

    /**
     * Local cache for document properties.
     */
    private array $properties = [];

    /**
     * @param DocumentLocator $documentLocator   Used for resolving namespaces
     * @param string          $languageSeparator String separating the language code from the ML property name
     * @param array           $languages         List of all supported languages
     */
    public function __construct(
        private readonly DocumentLocator $documentLocator,
        private readonly string $languageSeparator,
        private readonly array $languages = [],
    ) {
    }

    /**
     * Parses a document class and returns its metadata.
     *
     * @throws \ReflectionException
     */
    public function parse(\ReflectionClass $documentReflection, array $indexAnalyzers): array
    {
        $documentAttributes = $documentReflection->getAttributes(Document::class);

        if (empty($documentAttributes)) {
            throw new InvalidMappingException(sprintf('Class "%s" must have the "%s" attribute in order to be used as a Document', $documentReflection->getName(), Document::class));
        }

        /** @var Document $documentAttribute */
        $documentAttribute = $documentAttributes[0]->newInstance();

        $properties = $this->getProperties($documentReflection, $indexAnalyzers);

        return [
            'properties'         => $properties,
            'fields'             => \array_filter($documentAttribute->dump()),
            'propertiesMetadata' => $this->getPropertiesMetadata($documentReflection),
            'repositoryClass'    => $documentAttribute->repositoryClass,
            'providerClass'      => $documentAttribute->providerClass,
            'className'          => $documentReflection->getName(),
        ];
    }

    /**
     * Finds properties' metadata for every property used in document or inner/nested object
     *
     * @throws InvalidMappingException
     * @throws \ReflectionException
     * @throws \LogicException
     */
    public function getPropertiesMetadata(\ReflectionClass $documentReflection): array
    {
        $className = $documentReflection->getName();
        if (\array_key_exists($className, $this->propertiesMetadata)) {
            return $this->propertiesMetadata[$className];
        }

        $propertyMetadata = [];

        /** @var \ReflectionProperty $propertyReflection */
        foreach ($this->getDocumentPropertiesReflection($documentReflection) as $propertyName => $propertyReflection) {
            $propertyAttributes = $propertyReflection->getAttributes(Property::class);
            $propertyAttributes = $propertyAttributes ?: $propertyReflection->getAttributes(Id::class);
            $propertyAttributes = $propertyAttributes ?: $propertyReflection->getAttributes(Score::class);

            // Ignore class properties without any recognized attribute
            if (empty($propertyAttributes)) {
                continue;
            }

            /** @var PropertyAttributeInterface $propertyAttribute */
            $propertyAttribute = $propertyAttributes[0]->newInstance();

            switch ($propertyAttribute::class) {
                case Property::class:
                    $propertyMetadata[$propertyAttribute->name] = [
                        'propertyName' => $propertyName,
                        'type'         => $propertyAttribute->type,
                    ];
                    if ($propertyAttribute->multilanguage) {
                        $propertyMetadata[$propertyAttribute->name]['multilanguage'] = true;
                    }

                    // If property is a (nested) object
                    if (\in_array($propertyAttribute->type, ['object', 'nested'])) {
                        if (!$propertyAttribute->objectName) {
                            throw new InvalidMappingException(sprintf('Property "%s" in %s is missing "objectName" setting', $propertyName, $className));
                        }
                        $child = new \ReflectionClass($this->documentLocator->resolveClassName($propertyAttribute->objectName));
                        $propertyMetadata[$propertyAttribute->name] = \array_merge(
                            $propertyMetadata[$propertyAttribute->name],
                            [
                                'multiple'           => $propertyAttribute->multiple,
                                'propertiesMetadata' => $this->getPropertiesMetadata($child),
                                'className'          => $child->getName(),
                            ],
                        );
                    } else {
                        if (null !== $propertyAttribute->enumType) {
                            if (!enum_exists($propertyAttribute->enumType)) {
                                throw new InvalidMappingException(sprintf('Enum "%s" for property "%s" in %s does not exist', $propertyAttribute->enumType, $propertyName, $className));
                            }
                            $propertyMetadata[$propertyAttribute->name]['enumType'] = $propertyAttribute->enumType;
                        }
                    }
                    break;

                case Score::class:
                case Id::class:
                    $propertyMetadata[$propertyAttribute->getName()] = [
                        'propertyName' => $propertyName,
                        'type'         => $propertyAttribute->getType(),
                    ];
                    break;
            }

            if ($propertyReflection->isPublic()) {
                $propertyAccess = DocumentMetadata::PROPERTY_ACCESS_PUBLIC;
            } else {
                $propertyAccess = DocumentMetadata::PROPERTY_ACCESS_PRIVATE;
                $camelCaseName = \ucfirst(Caser::camel($propertyName));
                $setterMethod = 'set'.$camelCaseName;
                $getterMethod = 'get'.$camelCaseName;
                // Allow issers as getters for boolean properties
                if ('boolean' === $propertyAttribute->getType() && !$documentReflection->hasMethod($getterMethod)) {
                    $getterMethod = 'is'.$camelCaseName;
                }
                if ($documentReflection->hasMethod($getterMethod) && $documentReflection->hasMethod($setterMethod)) {
                    $propertyMetadata[$propertyAttribute->getName()]['methods'] = [
                        'getter' => $getterMethod,
                        'setter' => $setterMethod,
                    ];
                } else {
                    $message = sprintf('Property "%s" either needs to be public or %s() and %s() methods must be defined', $propertyName, $getterMethod, $setterMethod);
                    throw new \LogicException($message);
                }
            }

            $propertyMetadata[$propertyAttribute->getName()]['propertyAccess'] = $propertyAccess;
        }

        $this->propertiesMetadata[$className] = $propertyMetadata;

        return $this->propertiesMetadata[$className];
    }

    /**
     * Returns all defined properties, including the ones from parents.
     */
    private function getDocumentPropertiesReflection(\ReflectionClass $documentReflection): array
    {
        if (\in_array($documentReflection->getName(), $this->properties)) {
            return $this->properties[$documentReflection->getName()];
        }

        $properties = [];

        foreach ($documentReflection->getProperties() as $property) {
            if (!\in_array($property->getName(), $properties)) {
                $properties[$property->getName()] = $property;
            }
        }

        $parentReflection = $documentReflection->getParentClass();
        if (false !== $parentReflection) {
            $properties = \array_merge(
                $properties,
                \array_diff_key($this->getDocumentPropertiesReflection($parentReflection), $properties),
            );
        }

        $this->properties[$documentReflection->getName()] = $properties;

        return $properties;
    }

    /**
     * Returns properties of reflection class.
     *
     * @param \ReflectionClass $documentReflection class to read properties from
     *
     * @throws \ReflectionException
     * @throws InvalidMappingException
     */
    private function getProperties(\ReflectionClass $documentReflection, array $indexAnalyzers = []): array
    {
        $mapping = [];
        /** @var \ReflectionProperty $propertyReflection */
        foreach ($this->getDocumentPropertiesReflection($documentReflection) as $propertyReflection) {
            $propertyAttributes = $propertyReflection->getAttributes(Property::class);
            if (empty($propertyAttributes)) {
                continue;
            }

            /** @var Property $propertyAttribute */
            $propertyAttribute = $propertyAttributes[0]->newInstance();

            // If it is a multi-language property
            if (true === $propertyAttribute->multilanguage) {
                if (!\in_array($propertyAttribute->getType(), ['string', 'keyword', 'text'])) {
                    throw new InvalidMappingException(sprintf('"%s" property in %s is declared as multilanguage, so can only be of type "keyword", "text" or the deprecated "string"', $propertyAttribute->getName(), $documentReflection->getName()));
                }
                if (!$this->languages) {
                    throw new InvalidMappingException('There must be at least one language specified in sineflow_elasticsearch.languages in order to use multilanguage properties');
                }
                foreach ($this->languages as $language) {
                    $mapping[$propertyAttribute->getName().$this->languageSeparator.$language] = $this->getPropertyMapping($propertyAttribute, $language, $indexAnalyzers);
                }
                // TODO: The application should decide whether it wants to use a default field at all and set its mapping on a global base
                // The custom mapping from the application should be set here, using perhaps some kind of decorator
                $mapping[$propertyAttribute->getName().$this->languageSeparator.Property::DEFAULT_LANG_SUFFIX] = $propertyAttribute->multilanguageDefaultOptions ?: [
                    'type'         => 'keyword',
                    'ignore_above' => 256,
                ];
            } else {
                $mapping[$propertyAttribute->getName()] = $this->getPropertyMapping($propertyAttribute, null, $indexAnalyzers);
            }
        }

        return $mapping;
    }

    /**
     * @throws \ReflectionException
     */
    private function getPropertyMapping(Property $propertyAttribute, ?string $language = null, array $indexAnalyzers = []): array
    {
        $propertyMapping = $propertyAttribute->dump([
            'language'       => $language,
            'indexAnalyzers' => $indexAnalyzers,
        ]);

        // Inner/nested object
        if (\in_array($propertyAttribute->type, ['object', 'nested']) && !empty($propertyAttribute->objectName)) {
            $propertyMapping = \array_replace_recursive($propertyMapping, $this->getObjectMapping($propertyAttribute->objectName, $indexAnalyzers));
        }

        return $propertyMapping;
    }

    /**
     * Returns object mapping.
     *
     * @throws \ReflectionException
     */
    private function getObjectMapping(string $objectName, array $indexAnalyzers = []): array
    {
        $className = $this->documentLocator->resolveClassName($objectName);

        if (\array_key_exists($className, $this->objects)) {
            return $this->objects[$className];
        }

        $this->objects[$className] = $this->getRelationMapping(new \ReflectionClass($className), $indexAnalyzers);

        return $this->objects[$className];
    }

    /**
     * Returns relation mapping by its reflection.
     *
     * @throws \ReflectionException
     * @throws InvalidMappingException
     */
    private function getRelationMapping(\ReflectionClass $objectReflection, array $indexAnalyzers = []): array
    {
        $docObjectAttributes = $objectReflection->getAttributes(DocObject::class);
        if (empty($docObjectAttributes)) {
            throw new InvalidMappingException(sprintf('Class "%s" must have the "%s" attribute in order to be used as a nested object inside a Document', $objectReflection->getName(), DocObject::class));
        }

        return ['properties' => $this->getProperties($objectReflection, $indexAnalyzers)];
    }
}
