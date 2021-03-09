<?php

namespace Sineflow\ElasticsearchBundle\Mapping;

use Doctrine\Common\Cache\Cache;
use Sineflow\ElasticsearchBundle\Exception\Exception;

/**
 * Class for getting document metadata.
 */
class DocumentMetadataCollector
{
    /**
     * For caching the full metadata of all available document entities
     */
    const DOCUMENTS_CACHE_KEY = 'sfes.documents_metadata';

    /**
     * For caching just the properties metadata of a document or object entity
     */
    const OBJECTS_CACHE_KEY = 'sfes.object_properties_metadata.';

    /**
     * <document_class_FQN> => DocumentMetadata
     *
     * @var array
     */
    private $metadata = [];

    /**
     * <object_class_FQN> => [<properties_metadata>]
     *
     * @var array
     */
    private $objectsMetadata = [];

    /**
     * @var array
     *
     * <document_class_FQN> => <index_manager_name>
     */
    private $documentClassToIndexManagerNames = [];

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
     * @var int
     */
    private $documentsLastModifiedTime;

    /**
     * @var bool
     */
    private $isCacheEnabled;

    /**
     * @var bool
     */
    private $verifyCacheFreshness;

    /**
     * @param array           $indexManagers   The list of index managers defined
     * @param DocumentLocator $documentLocator For finding documents.
     * @param DocumentParser  $parser          For reading document annotations.
     * @param Cache|null      $cache           For caching documents metadata
     * @param bool            $debug
     *
     * @throws \ReflectionException
     */
    public function __construct(array $indexManagers, DocumentLocator $documentLocator, DocumentParser $parser, ?Cache $cache, bool $debug = false)
    {
        $this->indexManagers = $indexManagers;
        $this->documentLocator = $documentLocator;
        $this->parser = $parser;
        $this->cache = $cache;
        $this->isCacheEnabled = $cache instanceof Cache;
        $this->verifyCacheFreshness = $this->isCacheEnabled && $debug;

        if ($this->verifyCacheFreshness) {
            // Gets the time when the documents' folders were last modified
            $documentDirs = $this->documentLocator->getAllDocumentDirs();
            foreach ($documentDirs as $dir) {
                $this->documentsLastModifiedTime = max($this->documentsLastModifiedTime, filemtime($dir));
            }
        }

        if ($this->isCacheEnabled && $this->isCacheFresh(self::DOCUMENTS_CACHE_KEY)) {
            $this->metadata = $this->cache->fetch(self::DOCUMENTS_CACHE_KEY);
        }

        // If we have no metadata, build it now
        if (!$this->metadata) {
            $this->metadata = $this->fetchDocumentsMetadata();
        }

        // Build an internal array with map of document class to index manager name
        foreach ($this->indexManagers as $indexManagerName => $indexSettings) {
            $this->documentClassToIndexManagerNames[$this->documentLocator->resolveClassName($indexSettings['class'])] = $indexManagerName;
        }
    }

    /**
     * Returns metadata for the specified document class name.
     * Class can also be specified in short notation (e.g App:Product)
     *
     * @param string $documentClass
     *
     * @return DocumentMetadata
     */
    public function getDocumentMetadata(string $documentClass)
    {
        $documentClass = $this->documentLocator->resolveClassName($documentClass);

        if (!isset($this->metadata[$documentClass])) {
            throw new \InvalidArgumentException(sprintf('Metadata for [%s] is not available', $documentClass));
        }

        return $this->metadata[$documentClass];
    }

    /**
     * Returns metadata for the specified object class name
     * Class can also be specified in short notation (e.g App:ObjCategory)
     *
     * @param string $objectClass
     *
     * @return array
     *
     * @throws Exception
     * @throws \ReflectionException
     * @throws \UnexpectedValueException
     */
    public function getObjectPropertiesMetadata(string $objectClass)
    {
        $objectMetadata = null;

        $objectClass = $this->documentLocator->resolveClassName($objectClass);
        if (isset($this->objectsMetadata[$objectClass])) {
            return $this->objectsMetadata[$objectClass];
        }

        if ($this->isCacheEnabled && $this->isCacheFresh(self::OBJECTS_CACHE_KEY.$objectClass)) {
            $objectMetadata = $this->cache->fetch(self::OBJECTS_CACHE_KEY.$objectClass);
        }

        // Get the metadata the slow way
        if (!$objectMetadata) {
            $objectMetadata = $this->parser->getPropertiesMetadata(new \ReflectionClass($objectClass));
        }

        // Save the value for subsequent calls
        $this->objectsMetadata[$objectClass] = $objectMetadata;
        if ($this->isCacheEnabled) {
            $this->cache->save(self::OBJECTS_CACHE_KEY.$objectClass, $objectMetadata);
            $this->cache->save('[C]'.self::OBJECTS_CACHE_KEY.$objectClass, time());
        }

        return $objectMetadata;
    }

    /**
     * Returns the metadata of the documents within the specified index
     *
     * @param string $indexManagerName
     *
     * @return DocumentMetadata
     *
     * @throws \InvalidArgumentException
     */
    public function getDocumentMetadataForIndex(string $indexManagerName)
    {
        $documentClass = array_search($indexManagerName, $this->documentClassToIndexManagerNames);
        $indexMetadata = $this->metadata[$documentClass] ?? null;

        if (!$indexMetadata) {
            throw new \InvalidArgumentException(sprintf('No metadata found for index [%s]', $indexManagerName));
        }

        return $indexMetadata;
    }

    /**
     * Returns all document classes FQNs as keys and the corresponding index manager that manages them as values
     *
     * @param array $documentClasses If specified, will only return indices for those class names
     *
     * @return array
     */
    public function getIndexManagersForDocumentClasses(array $documentClasses = [])
    {
        if ($documentClasses) {
            $result = [];
            foreach ($documentClasses as $documentClass) {
                $documentClass = $this->documentLocator->resolveClassName($documentClass);
                if (isset($this->documentClassToIndexManagerNames[$documentClass])) {
                    $result[$documentClass] = $this->documentClassToIndexManagerNames[$documentClass];
                } else {
                    throw new \InvalidArgumentException(sprintf('Class [%s] is not managed by any index manager', $documentClass));
                }
            }
        } else {
            $result = $this->documentClassToIndexManagerNames;
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
    public function getDocumentClassIndex(string $documentClass)
    {
        $documentClass = $this->documentLocator->resolveClassName($documentClass);

        if (!isset($this->documentClassToIndexManagerNames[$documentClass])) {
            throw new \InvalidArgumentException(sprintf('Entity [%s] is not managed by any index manager', $documentClass));
        }

        return $this->documentClassToIndexManagerNames[$documentClass];
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
        $metadata = [];
        $indexManagerNames = [];

        foreach ($this->indexManagers as $indexManagerName => $indexSettings) {
            $indexAnalyzers = isset($indexSettings['settings']['analysis']['analyzer']) ? $indexSettings['settings']['analysis']['analyzer'] : [];

            // Fetch DocumentMetadata object for the entity in that index
            $documentClass = $this->documentLocator->resolveClassName($indexSettings['class']);
            if (isset($indexManagerNames[$documentClass])) {
                throw new \InvalidArgumentException(
                    sprintf(
                        'Index manager [%s] can not have class [%s], as that class is already specified for [%s]',
                        $indexManagerName,
                        $documentClass,
                        $indexManagerNames[$documentClass]
                    )
                );
            }
            $indexManagerNames[$documentClass] = $indexManagerName;
            $documentMetadataArray = $this->parser->parse(new \ReflectionClass($documentClass), $indexAnalyzers);

            $metadata[$documentClass] = new DocumentMetadata($documentMetadataArray);
        }

        if ($this->isCacheEnabled) {
            $this->cache->save(self::DOCUMENTS_CACHE_KEY, $metadata);
            $this->cache->save('[C]'.self::DOCUMENTS_CACHE_KEY, time());
        }

        return $metadata;
    }

    /**
     * Return whether cache for the given cache key is up-to-date
     *
     * @param string $cacheKey
     *
     * @return bool
     */
    private function isCacheFresh(string $cacheKey) : bool
    {
        if ($this->verifyCacheFreshness) {
            return $this->cache->fetch('[C]'.$cacheKey) > $this->documentsLastModifiedTime;
        }

        return true;
    }
}
