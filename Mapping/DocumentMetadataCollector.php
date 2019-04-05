<?php

namespace Sineflow\ElasticsearchBundle\Mapping;

use Doctrine\Common\Cache\Cache;
use Sineflow\ElasticsearchBundle\Exception\Exception;

/**
 * Class for getting type/document metadata.
 */
class DocumentMetadataCollector
{
    const DOCUMENTS_CACHE_KEY = 'sfes.documents_metadata';
    const OBJECTS_CACHE_KEY = 'sfes.objects_metadata.';

    /**
     * <index_manager_name> => [
     *      <document_class_short_name> => DocumentMetadata
     *      ...
     * ]
     * ...
     *
     * @var array
     */
    private $metadata = [];

    /**
     * <object_class_short_name> => [<properties_metadata>]
     * ...
     *
     * @var array
     */
    private $objectsMetadata = [];

    /**
     * @var array
     */
    private $indexManagers;

    /**
     * @var DocumentLocator
     */
    private $documentLocator;

    /**
     * @var DocumentParser
     */
    private $parser;

    /**
     * @var Cache
     */
    private $cache;

    /**
     * @var bool
     */
    private $debug;

    /**
     * @var int
     */
    private $documentsLastModifiedTime = 0;

    /**
     * @param array           $indexManagers   The list of index managers defined
     * @param DocumentLocator $documentLocator For finding documents.
     * @param DocumentParser  $parser          For reading document annotations.
     * @param Cache           $cache           For caching documents metadata
     * @param bool            $debug
     *
     * @throws \ReflectionException
     */
    public function __construct(array $indexManagers, DocumentLocator $documentLocator, DocumentParser $parser, Cache $cache, $debug = false)
    {
        $this->indexManagers = $indexManagers;
        $this->documentLocator = $documentLocator;
        $this->parser = $parser;
        $this->cache = $cache;
        $this->debug = (bool) $debug;

        // Gets the time when the documents' folders were last modified
        $documentDirs = $this->documentLocator->getAllDocumentDirs();
        foreach ($documentDirs as $dir) {
            $this->documentsLastModifiedTime = max($this->documentsLastModifiedTime, filemtime($dir));
        }

        $this->metadata = $this->cache->fetch(self::DOCUMENTS_CACHE_KEY);
        // If there was metadata in the cache
        if (false !== $this->metadata) {
            // If in debug mode and the cache has expired, don't use it
            if ($this->debug && !$this->isDocumentsCacheFresh()) {
                $this->metadata = false;
            }
        }

        // If there was no cached metadata, retrieve it now
        if (false === $this->metadata) {
            $this->fetchDocumentsMetadata();
        }
    }

    /**
     * Returns true if documents' metadata cache is up to date
     *
     * @return bool
     */
    private function isDocumentsCacheFresh()
    {
        return $this->cache->fetch('[C]'.self::DOCUMENTS_CACHE_KEY) > $this->documentsLastModifiedTime;
    }

    /**
     * Retrieves the metadata for all documents in all indices
     *
     * @return array
     *
     * @throws \ReflectionException
     */
    private function fetchDocumentsMetadata()
    {
        $this->metadata = [];
        $documentClasses = [];

        foreach ($this->indexManagers as $indexManagerName => $indexSettings) {
            $indexAnalyzers = isset($indexSettings['settings']['analysis']['analyzer']) ? $indexSettings['settings']['analysis']['analyzer'] : [];

            // Fetches DocumentMetadata objects for the types within the index
            foreach ($indexSettings['types'] as $documentClass) {
                if (isset($documentClasses[$documentClass])) {
                    throw new \InvalidArgumentException(
                        sprintf(
                            'You cannot have type %s under "%s" index manager, as it is already managed by "%s" index manager',
                            $documentClass,
                            $indexManagerName,
                            $documentClasses[$documentClass]
                        )
                    );
                }
                $documentClasses[$documentClass] = $indexManagerName;
                $metadata = $this->fetchMetadataFromClass($documentClass, $indexAnalyzers);
                $this->metadata[$indexManagerName][$documentClass] = new DocumentMetadata($metadata);
            }
        }

        $this->cache->save(self::DOCUMENTS_CACHE_KEY, $this->metadata);
        if ($this->debug) {
            $this->cache->save('[C]'.self::DOCUMENTS_CACHE_KEY, time());
        }

        return $this->metadata;
    }

    /**
     * Returns metadata for the specified document class name.
     * Class can also be specified in short notation (e.g AppBundle:Product)
     *
     * @param string $documentClass
     *
     * @return DocumentMetadata
     */
    public function getDocumentMetadata($documentClass)
    {
        $documentClass = $this->documentLocator->getShortClassName($documentClass);
        foreach ($this->metadata as $index => $types) {
            foreach ($types as $typeDocumentClass => $documentMetadata) {
                if ($documentClass === $typeDocumentClass) {
                    return $documentMetadata;
                }
            }
        }
        throw new \InvalidArgumentException(sprintf('Metadata for type "%s" is not available', $documentClass));
    }

    /**
     * Returns metadata for the specified object class name
     * Class can also be specified in short notation (e.g AppBundle:ObjCategory)
     *
     * @param string $objectClass
     *
     * @return array
     *
     * @throws Exception
     * @throws \ReflectionException
     */
    public function getObjectPropertiesMetadata($objectClass)
    {
        $objectMetadata = null;

        $objectClass = $this->documentLocator->getShortClassName($objectClass);
        if (isset($this->objectsMetadata[$objectClass])) {
            return $this->objectsMetadata[$objectClass];
        }

        // If we have it cached and up-to-date, get the data from cache
        if ($this->cache->fetch('[C]'.self::OBJECTS_CACHE_KEY.$objectClass) > $this->documentsLastModifiedTime) {
            $objectMetadata = $this->cache->fetch(self::OBJECTS_CACHE_KEY.$objectClass);
        }

        // Get the metadata the slow way and put it in the cache
        if (!$objectMetadata) {
            $objectMetadata = $this->parser->getPropertiesMetadata(new \ReflectionClass($this->documentLocator->resolveClassName($objectClass)));
            $this->cache->save(self::OBJECTS_CACHE_KEY.$objectClass, $objectMetadata);
            $this->cache->save('[C]'.self::OBJECTS_CACHE_KEY.$objectClass, time());
        }

        if (!is_array($objectMetadata)) {
            throw new Exception(sprintf('Metadata for object "%s" could not be retrieved', $objectClass));
        }

        // Cache the value in the class as well
        $this->objectsMetadata[$objectClass] = $objectMetadata;

        return $objectMetadata;
    }

    /**
     * Returns the metadata of the documents within the specified index
     *
     * @param string $indexManagerName
     *
     * @return DocumentMetadata[]
     *
     * @throws \InvalidArgumentException
     */
    public function getDocumentsMetadataForIndex($indexManagerName)
    {
        if (!isset($this->metadata[$indexManagerName])) {
            throw new \InvalidArgumentException(sprintf('No metadata found for index "%s"', $indexManagerName));
        }

        $indexMetadata = $this->metadata[$indexManagerName];

        return $indexMetadata;
    }

    /**
     * Return mapping of document classes in short notation (i.e. AppBundle:Product) to ES types
     *
     * @param array $documentClasses Only return those classes if specified
     *
     * @return array
     */
    public function getDocumentClassesTypes(array $documentClasses = [])
    {
        $result = [];
        foreach ($this->metadata as $index => $documentsMetadata) {
            foreach ($documentsMetadata as $documentClass => $documentMetadata) {
                /** @var DocumentMetadata $documentMetadata */
                $result[$documentClass] = $documentMetadata->getType();
            }
        }

        if ($documentClasses) {
            $result = array_intersect_key($result, array_flip($documentClasses));
        }

        return $result;
    }

    /**
     * Returns all document classes as keys and the corresponding index manager that manages them as values
     *
     * @param array $documentClasses Only return those classes if specified
     *
     * @return array
     */
    public function getDocumentClassesIndices(array $documentClasses = [])
    {
        $result = [];
        foreach ($this->metadata as $index => $documentsMetadata) {
            foreach ($documentsMetadata as $documentClass => $documentMetadata) {
                $result[$documentClass] = $index;
            }
        }

        if ($documentClasses) {
            $result = array_intersect_key($result, array_flip($documentClasses));
        }

        return $result;
    }

    /**
     * Returns the index manager name that manages the given entity document class
     *
     * @param string $documentClass Either as a fully qualified class name or a short notation
     *
     * @return string
     */
    public function getDocumentClassIndex($documentClass)
    {
        $documentClass = $this->documentLocator->getShortClassName($documentClass);

        $indices = $this->getDocumentClassesIndices();
        if (!isset($indices[$documentClass])) {
            throw new \InvalidArgumentException(sprintf('Entity "%s" is not managed by any index manager', $documentClass));
        }

        return $indices[$documentClass];
    }

    /**
     * Returns document mapping with metadata
     *
     * @param string $documentClassName
     * @param array  $indexAnalyzers
     *
     * @return array
     *
     * @throws \ReflectionException
     */
    public function fetchMetadataFromClass($documentClassName, array $indexAnalyzers)
    {
        $metadata = $this->getDocumentReflectionMetadata(
            new \ReflectionClass($this->documentLocator->resolveClassName($documentClassName)),
            $indexAnalyzers
        );

        return $metadata;
    }

    /**
     * Gathers annotation data from class.
     *
     * @param \ReflectionClass $documentReflection Document reflection class to read mapping from.
     * @param array            $indexAnalyzers
     *
     * @return array
     */
    private function getDocumentReflectionMetadata(\ReflectionClass $documentReflection, array $indexAnalyzers)
    {
        $metadata = $this->parser->parse($documentReflection, $indexAnalyzers);

        return $metadata;
    }
}
