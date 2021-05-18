<?php

namespace Sineflow\ElasticsearchBundle\Mapping;

use Doctrine\Common\Annotations\AnnotationRegistry;
use Doctrine\Common\Annotations\Reader;
use Sineflow\ElasticsearchBundle\Annotation\DocObject;
use Sineflow\ElasticsearchBundle\Annotation\Document;
use Sineflow\ElasticsearchBundle\Annotation\Id;
use Sineflow\ElasticsearchBundle\Annotation\Property;
use Sineflow\ElasticsearchBundle\Annotation\Score;

/**
 * Document parser used for reading document annotations.
 */
class DocumentParser
{
    /**
     * @var Reader Used to read document annotations.
     */
    private $reader;

    /**
     * @var DocumentLocator Used to find documents.
     */
    private $documentLocator;

    /**
     * @var array Contains gathered objects which later adds to documents.
     */
    private $objects = [];

    /**
     * @var array Document properties metadata.
     */
    private $propertiesMetadata = [];

    /**
     * @var array Local cache for document properties.
     */
    private $properties = [];

    /**
     * @var string
     */
    private $languageSeparator;

    /**
     * @var array
     */
    private $languages;

    /**
     * @param Reader          $reader            Used for reading annotations.
     * @param DocumentLocator $documentLocator   Used for resolving namespaces.
     * @param string          $languageSeparator String separating the language code from the ML property name
     */
    public function __construct(Reader $reader, DocumentLocator $documentLocator, string $languageSeparator, array $languages = [])
    {
        $this->reader = $reader;
        $this->documentLocator = $documentLocator;
        $this->languageSeparator = $languageSeparator;
        $this->languages = $languages;
        $this->registerAnnotations();
    }

    /**
     * Parses document by used annotations and returns mapping for elasticsearch with some extra metadata.
     *
     * @param \ReflectionClass $documentReflection
     * @param array            $indexAnalyzers
     *
     * @return array
     *
     * @throws \ReflectionException
     */
    public function parse(\ReflectionClass $documentReflection, array $indexAnalyzers)
    {
        $metadata = [];

        /** @var Document $classAnnotation */
        $classAnnotation = $this->reader->getClassAnnotation($documentReflection, Document::class);

        if (null !== $classAnnotation) {
            $properties = $this->getProperties($documentReflection, $indexAnalyzers);

            $metadata = [
                'properties' => $properties,
                'fields' => array_filter($classAnnotation->dump()),
                'propertiesMetadata' => $this->getPropertiesMetadata($documentReflection),
                'repositoryClass' => $classAnnotation->repositoryClass,
                'providerClass' => $classAnnotation->providerClass,
                'className' => $documentReflection->getName(),
            ];
        }

        return $metadata;
    }

    /**
     * Finds properties' metadata for every property used in document or inner/nested object
     *
     * @param \ReflectionClass $documentReflection
     *
     * @return array
     *
     * @throws \ReflectionException
     */
    public function getPropertiesMetadata(\ReflectionClass $documentReflection)
    {
        $className = $documentReflection->getName();
        if (array_key_exists($className, $this->propertiesMetadata)) {
            return $this->propertiesMetadata[$className];
        }

        $propertyMetadata = [];

        /** @var \ReflectionProperty $property */
        foreach ($this->getDocumentPropertiesReflection($documentReflection) as $propertyName => $property) {
            $propertyAnnotation = $this->getPropertyAnnotationData($property);
            $propertyAnnotation = $propertyAnnotation ?: $this->reader->getPropertyAnnotation($property, Id::class);
            $propertyAnnotation = $propertyAnnotation ?: $this->reader->getPropertyAnnotation($property, Score::class);

            // Ignore class properties without any recognized annotation
            if (null === $propertyAnnotation) {
                continue;
            }

            switch (get_class($propertyAnnotation)) {
                case Property::class:
                    $propertyMetadata[$propertyAnnotation->name] = [
                        'propertyName' => $propertyName,
                        'type' => $propertyAnnotation->type,
                        'multilanguage' => $propertyAnnotation->multilanguage,
                    ];

                    // If property is a (nested) object
                    if (in_array($propertyAnnotation->type, ['object', 'nested'])) {
                        if (!$propertyAnnotation->objectName) {
                            throw new \InvalidArgumentException(
                                sprintf('Property "%s" in %s is missing "objectName" setting', $propertyName, $className)
                            );
                        }
                        $child = new \ReflectionClass($this->documentLocator->resolveClassName($propertyAnnotation->objectName));
                        $propertyMetadata[$propertyAnnotation->name] = array_merge(
                            $propertyMetadata[$propertyAnnotation->name],
                            [
                                'multiple' => $propertyAnnotation->multiple,
                                'propertiesMetadata' => $this->getPropertiesMetadata($child),
                                'className' => $child->getName(),
                            ]
                        );
                    }
                    break;

                case Id::class:
                    $propertyAnnotation->name = '_id';
                    $propertyAnnotation->type = 'keyword';
                    $propertyMetadata[$propertyAnnotation->name] = [
                        'propertyName' => $propertyName,
                        'type' => $propertyAnnotation->type,
                    ];
                    break;

                case Score::class:
                    $propertyAnnotation->name = '_score';
                    $propertyAnnotation->type = 'float';
                    $propertyMetadata[$propertyAnnotation->name] = [
                        'propertyName' => $propertyName,
                        'type' => $propertyAnnotation->type,
                    ];
                    break;
            }

            if ($property->isPublic()) {
                $propertyAccess = DocumentMetadata::PROPERTY_ACCESS_PUBLIC;
            } else {
                $propertyAccess = DocumentMetadata::PROPERTY_ACCESS_PRIVATE;
                $camelCaseName = ucfirst(Caser::camel($propertyName));
                $setterMethod = 'set'.$camelCaseName;
                $getterMethod = 'get'.$camelCaseName;
                // Allow issers as getters for boolean properties
                if ($propertyAnnotation->type === 'boolean' && !$documentReflection->hasMethod($getterMethod)) {
                    $getterMethod = 'is'.$camelCaseName;
                }
                if ($documentReflection->hasMethod($getterMethod) && $documentReflection->hasMethod($setterMethod)) {
                    $propertyMetadata[$propertyAnnotation->name]['methods'] = [
                        'getter' => $getterMethod,
                        'setter' => $setterMethod,
                    ];
                } else {
                    $message = sprintf('Property "%s" either needs to be public or %s() and %s() methods must be defined', $propertyName, $getterMethod, $setterMethod);
                    throw new \LogicException($message);
                }
            }

            $propertyMetadata[$propertyAnnotation->name]['propertyAccess'] = $propertyAccess;
        }

        $this->propertiesMetadata[$className] = $propertyMetadata;

        return $this->propertiesMetadata[$className];
    }

    /**
     * Returns property annotation data from reader.
     *
     * @param \ReflectionProperty $property
     *
     * @return Property|null
     */
    private function getPropertyAnnotationData($property)
    {
        /** @var Property $annotation */
        $annotation = $this->reader->getPropertyAnnotation($property, Property::class);

        return $annotation;
    }

    /**
     * Registers annotations to registry so that it could be used by reader.
     */
    private function registerAnnotations()
    {
        $annotations = [
            'Document',
            'Property',
            'DocObject',
            'Id',
            'Score',
        ];

        foreach ($annotations as $annotation) {
            AnnotationRegistry::registerFile(__DIR__."/../Annotation/{$annotation}.php");
        }
    }

    /**
     * Returns all defined properties including private from parents.
     *
     * @param \ReflectionClass $documentReflection
     *
     * @return array
     */
    private function getDocumentPropertiesReflection(\ReflectionClass $documentReflection)
    {
        if (in_array($documentReflection->getName(), $this->properties)) {
            return $this->properties[$documentReflection->getName()];
        }

        $properties = [];

        foreach ($documentReflection->getProperties() as $property) {
            if (!in_array($property->getName(), $properties)) {
                $properties[$property->getName()] = $property;
            }
        }

        $parentReflection = $documentReflection->getParentClass();
        if (false !== $parentReflection) {
            $properties = array_merge(
                $properties,
                array_diff_key($this->getDocumentPropertiesReflection($parentReflection), $properties)
            );
        }

        $this->properties[$documentReflection->getName()] = $properties;

        return $properties;
    }

    /**
     * Returns properties of reflection class.
     *
     * @param \ReflectionClass $documentReflection Class to read properties from.
     * @param array            $indexAnalyzers
     *
     * @return array
     *
     * @throws \ReflectionException
     */
    private function getProperties(\ReflectionClass $documentReflection, array $indexAnalyzers = [])
    {
        $mapping = [];
        /** @var \ReflectionProperty $property */
        foreach ($this->getDocumentPropertiesReflection($documentReflection) as $propertyName => $property) {
            $propertyAnnotation = $this->getPropertyAnnotationData($property);

            if (empty($propertyAnnotation)) {
                continue;
            }

            // If it is a multi-language property
            if (true === $propertyAnnotation->multilanguage) {
                if (!in_array($propertyAnnotation->type, ['string', 'keyword', 'text'])) {
                    throw new \InvalidArgumentException(sprintf('"%s" property in %s is declared as multilanguage, so can only be of type "keyword", "text" or the deprecated "string"', $propertyAnnotation->name, $documentReflection->getName()));
                }
                if (!$this->languages) {
                    throw new \InvalidArgumentException('There must be at least one language specified in sineflow_elasticsearch.languages in order to use multilanguage properties');
                }
                foreach ($this->languages as $language) {
                    $mapping[$propertyAnnotation->name.$this->languageSeparator.$language] = $this->getPropertyMapping($propertyAnnotation, $language, $indexAnalyzers);
                }
                // TODO: The application should decide whether it wants to use a default field at all and set its mapping on a global base
                // The custom mapping from the application should be set here, using perhaps some kind of decorator
                $mapping[$propertyAnnotation->name.$this->languageSeparator.Property::DEFAULT_LANG_SUFFIX] = $propertyAnnotation->multilanguageDefaultOptions ?: [
                    'type' => 'keyword',
                    'ignore_above' => 256,
                ];
            } else {
                $mapping[$propertyAnnotation->name] = $this->getPropertyMapping($propertyAnnotation, null, $indexAnalyzers);
            }
        }

        return $mapping;
    }

    /**
     * @param Property    $propertyAnnotation
     * @param string|null $language
     * @param array       $indexAnalyzers
     *
     * @return array
     *
     * @throws \ReflectionException
     */
    private function getPropertyMapping(Property $propertyAnnotation, $language = null, array $indexAnalyzers = [])
    {
        $propertyMapping = $propertyAnnotation->dump([
            'language' => $language,
            'indexAnalyzers' => $indexAnalyzers,
        ]);

        // Inner/nested object
        if (in_array($propertyAnnotation->type, ['object', 'nested']) && !empty($propertyAnnotation->objectName)) {
            $propertyMapping = array_replace_recursive($propertyMapping, $this->getObjectMapping($propertyAnnotation->objectName, $indexAnalyzers));
        }

        return $propertyMapping;
    }

    /**
     * Returns object mapping.
     *
     * Loads from cache if it's already loaded.
     *
     * @param string $objectName
     * @param array  $indexAnalyzers
     *
     * @return array
     *
     * @throws \ReflectionException
     */
    private function getObjectMapping($objectName, array $indexAnalyzers = [])
    {
        $className = $this->documentLocator->resolveClassName($objectName);

        if (array_key_exists($className, $this->objects)) {
            return $this->objects[$className];
        }

        $this->objects[$className] = $this->getRelationMapping(new \ReflectionClass($className), $indexAnalyzers);

        return $this->objects[$className];
    }

    /**
     * Returns relation mapping by its reflection.
     *
     * @param \ReflectionClass $documentReflection
     * @param array            $indexAnalyzers
     *
     * @return array|null
     *
     * @throws \ReflectionException
     */
    private function getRelationMapping(\ReflectionClass $documentReflection, $indexAnalyzers = [])
    {
        if ($this->reader->getClassAnnotation($documentReflection, DocObject::class)) {
            return ['properties' => $this->getProperties($documentReflection, $indexAnalyzers)];
        }

        return null;
    }
}
