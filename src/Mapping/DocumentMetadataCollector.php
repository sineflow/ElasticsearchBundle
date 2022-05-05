<?php

namespace Sineflow\ElasticsearchBundle\Mapping;

use Symfony\Component\HttpKernel\CacheWarmer\WarmableInterface;
use Symfony\Contracts\Cache\CacheInterface;
use Symfony\Contracts\Cache\ItemInterface;

/**
 * Class for getting document metadata.
 */
class DocumentMetadataCollector implements WarmableInterface
{
    /**
     * For caching the full metadata of all available document entities
     */
    public const DOCUMENTS_CACHE_KEY_PREFIX = 'sfes.documents_metadata.';

    /**
     * For caching just the properties metadata of a document or object entity
     */
    public const OBJECTS_CACHE_KEY_PREFIX = 'sfes.object_properties_metadata.';

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
     * @var CacheInterface
     */
    private $cache;

    /**
     * @param array          $indexManagers The list of index managers defined
     * @param DocumentParser $parser        For reading entity annotations
     * @param CacheInterface $cache         For caching entity metadata
     */
    public function __construct(array $indexManagers, DocumentLocator $documentLocator, DocumentParser $parser, CacheInterface $cache)
    {
        $this->indexManagers = $indexManagers;
        $this->documentLocator = $documentLocator;
        $this->parser = $parser;
        $this->cache = $cache;

        // Build an internal array with map of document class to index manager name
        foreach ($this->indexManagers as $indexManagerName => $indexSettings) {
            $documentClass = $this->documentLocator->resolveClassName($indexSettings['class']);

            if (isset($this->documentClassToIndexManagerNames[$documentClass])) {
                throw new \InvalidArgumentException(\sprintf('Index manager [%s] can not have class [%s], as that class is already specified for [%s]', $indexManagerName, $documentClass, $this->documentClassToIndexManagerNames[$documentClass]));
            }

            $this->documentClassToIndexManagerNames[$documentClass] = $indexManagerName;
        }
    }

    /**
     * Warms up the cache.
     *
     * @param string $cacheDir
     *
     * @return string[]
     *
     * @throws \Psr\Cache\InvalidArgumentException
     */
    public function warmUp($cacheDir)
    {
        // force cache generation
        foreach ($this->documentClassToIndexManagerNames as $documentClass => $indexManagerName) {
            $documentMetadata = $this->getDocumentMetadata($documentClass);

            $recursive = new \RecursiveIteratorIterator(new \RecursiveArrayIterator($documentMetadata->getPropertiesMetadata()));
            foreach ($recursive as $key => $value) {
                if ('className' === $key) {
                    $this->getObjectPropertiesMetadata($value);
                }
            }
        }

        return [];
    }

    /**
     * Returns metadata for the specified document class name.
     * Class can also be specified in short notation (e.g App:Product)
     *
     * @throws \Psr\Cache\InvalidArgumentException
     */
    public function getDocumentMetadata(string $documentClass): DocumentMetadata
    {
        $documentClass = $this->documentLocator->resolveClassName($documentClass);

        $cacheKey = self::DOCUMENTS_CACHE_KEY_PREFIX.\strtr($documentClass, '\\', '.');

        return $this->cache->get($cacheKey, function (ItemInterface $item) use ($documentClass) {
            return $this->fetchDocumentMetadata($documentClass);
        }, 0);
    }

    /**
     * Returns metadata for the specified object class name
     * Class can also be specified in short notation (e.g App:ObjCategory)
     *
     * @throws \Psr\Cache\InvalidArgumentException
     */
    public function getObjectPropertiesMetadata(string $objectClass): array
    {
        $objectClass = $this->documentLocator->resolveClassName($objectClass);

        $cacheKey = self::OBJECTS_CACHE_KEY_PREFIX.\strtr($objectClass, '\\', '.');

        return $this->cache->get($cacheKey, function (ItemInterface $item) use ($objectClass) {
            return $this->parser->getPropertiesMetadata(new \ReflectionClass($objectClass));
        }, 0);
    }

    /**
     * Returns the index manager name that manages the given entity document class
     *
     * @param string $documentClass Either as a fully qualified class name or a short notation
     */
    public function getDocumentClassIndex(string $documentClass): string
    {
        $documentClass = $this->documentLocator->resolveClassName($documentClass);

        if (!isset($this->documentClassToIndexManagerNames[$documentClass])) {
            throw new \InvalidArgumentException(\sprintf('Entity [%s] is not managed by any index manager. You need an entry in the sineflow_elasticsearch.indices config key with this class.', $documentClass));
        }

        return $this->documentClassToIndexManagerNames[$documentClass];
    }

    /**
     * Retrieves the metadata for a document
     *
     * @throws \ReflectionException
     */
    private function fetchDocumentMetadata(string $documentClass): DocumentMetadata
    {
        $documentClass = $this->documentLocator->resolveClassName($documentClass);
        $indexManagerName = $this->getDocumentClassIndex($documentClass);
        $indexAnalyzers = $this->indexManagers[$indexManagerName]['settings']['analysis']['analyzer'] ?? [];
        $documentMetadataArray = $this->parser->parse(new \ReflectionClass($documentClass), $indexAnalyzers);

        return new DocumentMetadata($documentMetadataArray);
    }
}
