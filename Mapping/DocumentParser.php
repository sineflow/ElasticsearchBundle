<?php

namespace Sineflow\ElasticsearchBundle\Mapping;

use Doctrine\Common\Annotations\AnnotationRegistry;
use Doctrine\Common\Annotations\Reader;
use Sineflow\ElasticsearchBundle\Annotation\DocObject;
use Sineflow\ElasticsearchBundle\Annotation\Document;
use Sineflow\ElasticsearchBundle\Annotation\Id;
use Sineflow\ElasticsearchBundle\Annotation\ParentId;
use Sineflow\ElasticsearchBundle\Annotation\Property;
use Sineflow\ElasticsearchBundle\Annotation\Score;
use Sineflow\ElasticsearchBundle\LanguageProvider\LanguageProviderInterface;

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
     * @var LanguageProviderInterface
     */
    private $languageProvider;

    /**
     * @var string
     */
    private $languageSeparator;

    /**
     * @param Reader          $reader            Used for reading annotations.
     * @param DocumentLocator $documentLocator   Used for resolving namespaces.
     * @param string          $languageSeparator String separating the language code from the ML property name
     */
    public function __construct(Reader $reader, DocumentLocator $documentLocator, $languageSeparator)
    {
        $this->reader = $reader;
        $this->documentLocator = $documentLocator;
        $this->languageSeparator = $languageSeparator;
        $this->registerAnnotations();
    }

    /**
     * @param LanguageProviderInterface $languageProvider
     */
    public function setLanguageProvider(LanguageProviderInterface $languageProvider)
    {
        $this->languageProvider = $languageProvider;
    }

    /**
     * Parses document by used annotations and returns mapping for elasticsearch with some extra metadata.
     *
     * @param \ReflectionClass $documentReflection
     * @param array            $indexAnalyzers
     *
     * @return array
     */
    public function parse(\ReflectionClass $documentReflection, array $indexAnalyzers)
    {
        $metadata = [];

        /** @var Document $classAnnotation */
        $classAnnotation = $this->reader->getClassAnnotation($documentReflection, Document::class);

        if ($classAnnotation !== null) {
            if ($classAnnotation->parent !== null) {
                $parent = $this->getDocumentType(
                    new \ReflectionClass($this->documentLocator->resolveClassName($classAnnotation->parent))
                );
            } else {
                $parent = null;
            }
            $type = $this->getDocumentType($documentReflection);

            $properties = $this->getProperties($documentReflection, $indexAnalyzers);

            $metadata = [
                'type' => $type,
                'properties' => $properties,
                'fields' => array_filter(
                    array_merge(
                        $classAnnotation->dump(),
                        ['_parent' => $parent === null ? null : ['type' => $parent]]
                    )
                ),
                'propertiesMetadata' => $this->getPropertiesMetadata($documentReflection),
                'objects' => $this->getObjects(),
                'repositoryClass' => $classAnnotation->repositoryClass,
                'className' => $documentReflection->getName(),
                'shortClassName' => $this->documentLocator->getShortClassName($documentReflection->getName()),
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
            $propertyAnnotation = $propertyAnnotation ?: $this->reader->getPropertyAnnotation($property, ParentId::class);
            $propertyAnnotation = $propertyAnnotation ?: $this->reader->getPropertyAnnotation($property, Score::class);

            // Ignore class properties without any recognized annotation
            if ($propertyAnnotation === null) {
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

                case ParentId::class:
                    $propertyAnnotation->name = '_parent';
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
     * Returns document's elasticsearch type.
     *
     * @param \ReflectionClass $documentReflection
     *
     * @return string
     */
    private function getDocumentType(\ReflectionClass $documentReflection)
    {
        /** @var Document $classAnnotation */
        $classAnnotation = $this->reader->getClassAnnotation($documentReflection, Document::class);

        // If an Elasticsearch type is not defined in the entity annotation, use the lowercased class name as such
        return empty($classAnnotation->type) ? strtolower($documentReflection->getShortName()) : $classAnnotation->type;
    }

    /**
     * Returns property annotation data from reader.
     *
     * @param \ReflectionProperty $property
     *
     * @return Property
     */
    private function getPropertyAnnotationData($property)
    {
        $annotation = $this->reader->getPropertyAnnotation($property, Property::class);

        return $annotation;
    }

    /**
     * Returns objects used in document.
     *
     * @return array
     */
    private function getObjects()
    {
        return array_keys($this->objects);
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
            'Object',
            'Id',
            'ParentId',
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
        if ($parentReflection !== false) {
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
                if (!$this->languageProvider) {
                    throw new \InvalidArgumentException('There must be a service tagged as "sfes.language_provider" in order to use multilanguage properties');
                }
                foreach ($this->languageProvider->getLanguages() as $language) {
                    $mapping[$propertyAnnotation->name.$this->languageSeparator.$language] = $this->getPropertyMapping($propertyAnnotation, $language, $indexAnalyzers);
                }
                // TODO: The application should decide whether it wants to use a default field at all and set its mapping on a global base (or per property?)
                // The custom mapping from the application should be set here, using perhaps some kind of decorator
                $mapping[$propertyAnnotation->name.$this->languageSeparator.Property::DEFAULT_LANG_SUFFIX] = [
                    'type' => 'keyword',
                ];
            } else {
                $mapping[$propertyAnnotation->name] = $this->getPropertyMapping($propertyAnnotation, null, $indexAnalyzers);
            }
        }

        return $mapping;
    }

    private function getPropertyMapping(Property $propertyAnnotation, $language = null, array $indexAnalyzers = [])
    {
        $propertyMapping = $propertyAnnotation->dump([
            'language' => $language,
            'indexAnalyzers' => $indexAnalyzers,
        ]);

        // Object.
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
     */
    private function getRelationMapping(\ReflectionClass $documentReflection, $indexAnalyzers = [])
    {
        if ($this->reader->getClassAnnotation($documentReflection, DocObject::class)) {
            return ['properties' => $this->getProperties($documentReflection, $indexAnalyzers)];
        }

        return null;
    }
}
