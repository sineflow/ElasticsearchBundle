<?php

namespace Sineflow\ElasticsearchBundle\Manager;

use Elasticsearch\Common\Exceptions\Missing404Exception;
use Sineflow\ElasticsearchBundle\Document\DocumentInterface;
use Sineflow\ElasticsearchBundle\Document\Provider\ProviderInterface;
use Sineflow\ElasticsearchBundle\Document\Provider\ProviderRegistry;
use Sineflow\ElasticsearchBundle\Document\Repository\Repository;
use Sineflow\ElasticsearchBundle\Event\Events;
use Sineflow\ElasticsearchBundle\Event\PrePersistEvent;
use Sineflow\ElasticsearchBundle\Exception\BulkRequestException;
use Sineflow\ElasticsearchBundle\Exception\Exception;
use Sineflow\ElasticsearchBundle\Exception\IndexRebuildingException;
use Sineflow\ElasticsearchBundle\Exception\NoReadAliasException;
use Sineflow\ElasticsearchBundle\Finder\Finder;
use Sineflow\ElasticsearchBundle\Mapping\DocumentMetadataCollector;
use Sineflow\ElasticsearchBundle\Result\DocumentConverter;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;

/**
 * Manager class.
 */
class IndexManager
{
    /**
     * @var string The unique manager name (the key from the index configuration)
     */
    protected $managerName;

    /**
     * @var ConnectionManager Elasticsearch connection.
     */
    protected $connection;

    /**
     * @var DocumentMetadataCollector
     */
    protected $metadataCollector;

    /**
     * @var ProviderRegistry
     */
    protected $providerRegistry;

    /**
     * @var Finder
     */
    protected $finder;

    /**
     * @var DocumentConverter
     */
    protected $documentConverter;

    /**
     * @var array
     */
    protected $indexMapping;

    /**
     * @var Repository[]
     */
    protected $repositories = [];

    /**
     * @var bool Whether to use index aliases
     */
    protected $useAliases = true;

    /**
     * @var string The alias where data should be read from
     */
    protected $readAlias = null;

    /**
     * @var string The alias where data should be written to
     */
    protected $writeAlias = null;

    /**
     * @var EventDispatcherInterface
     */
    protected $eventDispatcher;

    /**
     * @param string                    $managerName
     * @param ConnectionManager         $connection
     * @param DocumentMetadataCollector $metadataCollector
     * @param ProviderRegistry          $providerRegistry
     * @param Finder                    $finder
     * @param DocumentConverter         $documentConverter
     * @param array                     $indexSettings
     */
    public function __construct(
        $managerName,
        ConnectionManager $connection,
        DocumentMetadataCollector $metadataCollector,
        ProviderRegistry $providerRegistry,
        Finder $finder,
        DocumentConverter $documentConverter,
        array $indexSettings
    ) {
        $this->managerName = $managerName;
        $this->connection = $connection;
        $this->metadataCollector = $metadataCollector;
        $this->providerRegistry = $providerRegistry;
        $this->finder = $finder;
        $this->documentConverter = $documentConverter;
        $this->useAliases = $indexSettings['use_aliases'];
        $this->indexMapping = $this->getIndexMapping($managerName, $indexSettings);

        $this->readAlias = $this->getBaseIndexName();
        $this->writeAlias = $this->getBaseIndexName();

        if (true === $this->useAliases) {
            $this->writeAlias .= '_write';
        }
    }

    /**
     * @return EventDispatcherInterface
     */
    public function getEventDispatcher()
    {
        return $this->eventDispatcher;
    }

    /**
     * @param EventDispatcherInterface $eventDispatcher
     */
    public function setEventDispatcher($eventDispatcher)
    {
        $this->eventDispatcher = $eventDispatcher;
    }

    /**
     * Returns mapping array for index
     *
     * @param string           $indexManagerName
     * @param array            $indexSettings
     * @return array
     */
    private function getIndexMapping($indexManagerName, array $indexSettings)
    {
        $index = ['index' => $indexSettings['name']];

        if (!empty($indexSettings['settings'])) {
            $index['body']['settings'] = $indexSettings['settings'];
        }

        $mappings = [];

        $metadata = $this->metadataCollector->getDocumentsMetadataForIndex($indexManagerName);
        foreach ($metadata as $className => $documentMetadata) {
            $mappings[$documentMetadata->getType()] = $documentMetadata->getClientMapping();
        }

        if (!empty($mappings)) {
            $index['body']['mappings'] = $mappings;
        }

        return $index;
    }

    /**
     * @return string
     */
    public function getManagerName()
    {
        return $this->managerName;
    }

    /**
     * @return bool
     */
    public function getUseAliases()
    {
        return $this->useAliases;
    }

    /**
     * Returns the 'read' alias when using aliases, or the index name, when not
     *
     * @return string
     */
    public function getReadAlias()
    {
        return $this->readAlias;
    }

    /**
     * Returns the 'write' alias when using aliases, or the index name, when not
     *
     * @return string
     */
    public function getWriteAlias()
    {
        return $this->writeAlias;
    }

    /**
     * @param string $writeAlias
     */
    private function setWriteAlias($writeAlias)
    {
        $this->writeAlias = $writeAlias;
    }

    /**
     * Returns Elasticsearch connection.
     *
     * @return ConnectionManager
     */
    public function getConnection()
    {
        return $this->connection;
    }

    /**
     * Returns repository for a document class
     *
     * @param string $documentClass
     *
     * @return Repository
     */
    public function getRepository($documentClass)
    {
        if (isset($this->repositories[$documentClass])) {
            return $this->repositories[$documentClass];
        }

        $repositoryClass = $this->metadataCollector->getDocumentMetadata($documentClass)->getRepositoryClass() ?: Repository::class;
        $repo = new $repositoryClass($this, $documentClass, $this->finder, $this->metadataCollector);

        if (!($repo instanceof Repository)) {
            throw new \InvalidArgumentException(sprintf('Repository "%s" must extend "%s"', $repositoryClass, Repository::class));
        }
        $this->repositories[$documentClass] = $repo;

        return $repo;
    }

    /**
     * Returns the data provider object for a type (provided in short class notation, e.g AppBundle:Product)
     *
     * @param string $documentClass The document class for the type
     * @return ProviderInterface
     */
    public function getDataProvider($documentClass)
    {
        $provider = $this->providerRegistry->getProviderInstance($documentClass);

        return $provider;
    }

    /**
     * Returns the base index name this manager is attached to.
     *
     * When using aliases, this would not represent an actual physical index.
     * getReadAlias() and getWriteAlias() should be used instead
     *
     * @return string
     */
    private function getBaseIndexName()
    {
        return $this->indexMapping['index'];
    }

    /**
     * Return a name for a new index, which does not already exist
     */
    private function getUniqueIndexName()
    {
        $indexName = $baseName = $this->getBaseIndexName().'_'.date('YmdHis');

        $i = 1;
        // Keep trying other names until there is no such existing index or alias
        while ($this->getConnection()->existsIndexOrAlias(array('index' => $indexName))) {
            $indexName = $baseName.'_'.$i;
            $i++;
        }

        return $indexName;
    }

    /**
     * Returns the live physical index name, verifying that it exists
     *
     * @return string
     * @throws Exception If live index is not found
     */
    public function getLiveIndex()
    {
        $indexName = null;

        // Get indices namespace of ES client
        $indices = $this->getConnection()->getClient()->indices();

        if (true === $this->getUseAliases()) {
            if ($this->getConnection()->existsAlias(['name' => $this->readAlias])) {
                $indexName = key($indices->getAlias(['name' => $this->readAlias]));
            } else {
                throw new Exception(sprintf('Index alias "%s" not found', $this->readAlias));
            }
        } else {
            $indexName = $this->getBaseIndexName();
        }

        if (!$this->getConnection()->existsIndexOrAlias(['index' => $indexName])) {
            throw new Exception(sprintf('Live index "%s" not found', $indexName));
        }

        return $indexName;
    }

    /**
     * Creates elasticsearch index and adds aliases to it depending on index settings
     *
     * @throws Exception
     */
    public function createIndex()
    {
        $settings = $this->indexMapping;

        if (true === $this->getUseAliases()) {
            // Make sure the read and write aliases do not exist already as aliases or physical indices
            if ($this->getConnection()->existsIndexOrAlias(array('index' => $this->readAlias))) {
                throw new Exception(sprintf('Read alias "%s" already exists as an alias or an index', $this->readAlias));
            }
            if ($this->getConnection()->existsIndexOrAlias(array('index' => $this->writeAlias))) {
                throw new Exception(sprintf('Write alias "%s" already exists as an alias or an index', $this->writeAlias));
            }

            // Create physical index with a unique name
            $settings['index'] = $this->getUniqueIndexName();
            $this->getConnection()->getClient()->indices()->create($settings);

            // Set aliases to index
            $setAliasParams = [
                'body' => [
                    'actions' => [
                        [
                            'add' => [
                                'index' => $settings['index'],
                                'alias' => $this->readAlias,
                            ],
                        ],
                        [
                            'add' => [
                                'index' => $settings['index'],
                                'alias' => $this->writeAlias,
                            ],
                        ],
                    ],
                ],
            ];
            $this->getConnection()->getClient()->indices()->updateAliases($setAliasParams);
        } else {
            // Make sure the index name does not exist already as a physical index or alias
            if ($this->getConnection()->existsIndexOrAlias(array('index' => $this->getBaseIndexName()))) {
                throw new Exception(sprintf('Index "%s" already exists as an alias or an index', $this->getBaseIndexName()));
            }
            $this->getConnection()->getClient()->indices()->create($settings);
        }
    }

    /**
     * Drops elasticsearch index(es).
     */
    public function dropIndex()
    {
        try {
            if (true === $this->getUseAliases()) {
                // Delete all physical indices aliased by the read and write aliases
                $aliasNames = $this->readAlias.','.$this->writeAlias;
                $indices = $this->getConnection()->getClient()->indices()->getAlias(array('name' => $aliasNames));
                $this->getConnection()->getClient()->indices()->delete(['index' => implode(',', array_keys($indices))]);
            } else {
                $this->getConnection()->getClient()->indices()->delete(['index' => $this->getBaseIndexName()]);
            }
        } catch (Missing404Exception $e) {
            // No physical indices exist for the index manager's aliases, or the physical index did not exist
        }
    }

    /**
     * Rebuilds ES Index and deletes the old one,
     *
     * @param bool $deleteOld             If set, the old index will be deleted upon successful rebuilding
     * @param bool $cancelExistingRebuild If set, any indices that the write alias points to (except the live one)
     *                                    will be deleted before the new build starts
     */
    public function rebuildIndex($deleteOld = false, $cancelExistingRebuild = false)
    {
        $batchSize = $this->connection->getConnectionSettings()['bulk_batch_size'];

        try {
            if (false === $this->getUseAliases()) {
                throw new Exception('Index rebuilding is not supported, unless you use aliases');
            }

            try {
                // Make sure the index and both aliases are properly set
                $this->verifyIndexAndAliasesState();
            } catch (NoReadAliasException $e) {
                // Looks like the index doesn't exist, so try to create an empty one
                $this->createIndex();
                // Now again make sure that everything is setup correctly
                $this->verifyIndexAndAliasesState();
            } catch (IndexRebuildingException $e) {
                if ($cancelExistingRebuild) {
                    // Delete the partial indices currently being rebuilt
                    foreach ($e->getIndices() as $partialIndex) {
                        $this->getConnection()->getClient()->indices()->delete(['index' => $partialIndex]);
                    }
                } else {
                    // Rethrow exception
                    throw $e;
                }
            }

            // Create a new index
            $settings = $this->indexMapping;
            $oldIndex = $this->getLiveIndex();
            $newIndex = $this->getUniqueIndexName();
            $settings['index'] = $newIndex;
            $this->getConnection()->getClient()->indices()->create($settings);

            // Point write alias to the new index as well
            $setAliasParams = [
                'body' => [
                    'actions' => [
                        [
                            'add' => [
                                'index' => $newIndex,
                                'alias' => $this->writeAlias,
                            ],
                        ],
                    ],
                ],
            ];
            $this->getConnection()->getClient()->indices()->updateAliases($setAliasParams);

            // Temporarily override the write alias with the new physical index name, so rebuilding only happens in the new index
            $originalWriteAlias = $this->writeAlias;
            $this->setWriteAlias($settings['index']);

            // Get and cycle all types for the index
            $indexDocumentsMetadata = $this->metadataCollector->getDocumentsMetadataForIndex($this->managerName);
            $documentClasses = array_keys($indexDocumentsMetadata);

            // Make sure we don't autocommit on every item in the bulk request
            $autocommit = $this->getConnection()->isAutocommit();
            $this->getConnection()->setAutocommit(false);

            foreach ($documentClasses as $documentClass) {
                $typeDataProvider = $this->getDataProvider($documentClass);
                $i = 1;
                foreach ($typeDataProvider->getDocuments() as $document) {
                    if (is_array($document)) {
                        $this->persistRaw($documentClass, $document);
                    } else {
                        $this->persist($document);
                    }
                    // Send the bulk request every X documents, so it doesn't get too big
                    if ($i % $batchSize == 0) {
                        $this->getConnection()->commit();
                    }
                    $i++;
                }
            }
            // Save any remaining documents to ES
            $this->getConnection()->commit();

            // Recover the autocommit mode as it was
            $this->getConnection()->setAutocommit($autocommit);

            // Restore write alias name
            $this->setWriteAlias($originalWriteAlias);

            // Point both aliases to the new index and remove them from the old
            $setAliasParams = [
                'body' => [
                    'actions' => [
                        [
                            'add' => [
                                'index' => $newIndex,
                                'alias' => $this->readAlias,
                            ],
                        ],
                        [
                            'remove' => [
                                'index' => $oldIndex,
                                'alias' => $this->readAlias,
                            ],
                        ],
                        [
                            'remove' => [
                                'index' => $oldIndex,
                                'alias' => $this->writeAlias,
                            ],
                        ],
                    ],
                ],
            ];
            $this->getConnection()->getClient()->indices()->updateAliases($setAliasParams);

            // Delete the old index
            if ($deleteOld) {
                $this->getConnection()->getClient()->indices()->delete(['index' => $oldIndex]);
                $this->getConnection()->getLogger()->notice(sprintf('Deleted old index %s', $oldIndex));
            }
        } catch (Exception $e) {
            // Bulk exceptions are logged in the connection manager, so only log other exceptions here
            if (!($e instanceof BulkRequestException)) {
                $this->getConnection()->getLogger()->error($e->getMessage());
            }

            // Try to delete the new incomplete index
            if (isset($newIndex)) {
                $this->getConnection()->getClient()->indices()->delete(['index' => $newIndex]);
                $this->getConnection()->getLogger()->notice(sprintf('Deleted incomplete index "%s"', $newIndex));
            }

            // Rethrow exception to be further handled
            throw $e;
        }
    }

    /**
     * Makes sure the index exists in Elasticsearch and its aliases (if using such) are properly set up
     *
     * @param bool|true $exceptionIfRebuilding
     * @throws NoReadAliasException     When read alias does not exist
     * @throws IndexRebuildingException When the index is rebuilding, according to the current aliases
     * @throws Exception                When any other problem with the index or aliases mappings exists
     */
    public function verifyIndexAndAliasesState($exceptionIfRebuilding = true)
    {
        if (false === $this->getUseAliases()) {
            // Check that the index exists
            if (!$this->getConnection()->existsIndexOrAlias(['index' => $this->getBaseIndexName()])) {
                throw new Exception(sprintf('Index "%s" does not exist', $this->getBaseIndexName()));
            }
        } else {
            $aliases = $this->getConnection()->getAliases();

            // Check that read alias exists
            if (!isset($aliases[$this->readAlias])) {
                throw new NoReadAliasException(sprintf('Read alias "%s" does not exist', $this->readAlias));
            }
            $liveIndex = key($aliases[$this->readAlias]);

            // Check that read alias points to exactly 1 index
            if (count($aliases[$this->readAlias]) > 1) {
                throw new Exception(sprintf('Read alias "%s" points to more than one index (%s)', $this->readAlias, implode(', ', $aliases[$this->readAlias])));
            }

            // Check that write alias exists
            if (!isset($aliases[$this->writeAlias])) {
                throw new Exception(sprintf('Write alias "%s" does not exist', $this->writeAlias));
            }

            // Check that write alias points to the same index as the read alias
            if (!isset($aliases[$this->writeAlias][$liveIndex])) {
                throw new Exception(sprintf('Write alias "%s" does not point to the live index "%s"', $this->writeAlias, $liveIndex));
            }

            // Check if write alias points to more than one index
            if ($exceptionIfRebuilding && count($aliases[$this->writeAlias]) > 1) {
                $writeAliasIndices = $aliases[$this->writeAlias];
                unset($writeAliasIndices[$liveIndex]);
                throw new IndexRebuildingException(
                    array_keys($writeAliasIndices),
                    sprintf('Index is currently being rebuilt as "%s"', implode(', ', array_keys($writeAliasIndices)))
                );
            }
        }
    }

    /**
     * Rebuilds the data of a document and adds it to a bulk request for the next commit.
     * Depending on the connection autocommit mode, the change may be committed right away.
     *
     * @param string $documentClass The document class in short notation (i.e. AppBundle:Product)
     * @param int    $id
     */
    public function reindex($documentClass, $id)
    {
        $documentMetadata = $this->metadataCollector->getDocumentMetadata($documentClass);

        $dataProvider = $this->getDataProvider($documentClass);
        $document = $dataProvider->getDocument($id);

        switch (true) {
            case $document instanceof DocumentInterface:
                if (get_class($document) !== $documentMetadata->getClassName()) {
                    throw new Exception(sprintf('Document must be "%s", but "%s" was returned from data provider', $documentMetadata->getClassName(), get_class($document)));
                }
                $this->persist($document);
                break;

            case is_array($document):
                if (!isset($document['_id'])) {
                    throw new Exception(sprintf('The returned document array must include an "_id" field: (%s)', serialize($document)));
                }
                if ($document['_id'] != $id) {
                    throw new Exception(sprintf('The document id must be "%s", but "%s" was returned from data provider', $id, $document['_id']));
                }
                $this->persistRaw($documentClass, $document);
                break;

            default:
                throw new Exception('Document must be either a DocumentInterface instance or an array with raw data');
        }

        if ($this->getConnection()->isAutocommit()) {
            $this->getConnection()->commit();
        }
    }

    /**
     * Adds document removal to a bulk request for the next commit.
     * Depending on the connection autocommit mode, the removal may be committed right away.
     *
     * @param string $documentClass The document class in short notation (i.e. AppBundle:Product)
     * @param string $id            Document ID to remove.
     *
     * @return array
     */
    public function delete($documentClass, $id)
    {
        $documentMetadata = $this->metadataCollector->getDocumentMetadata($documentClass);

        $this->getConnection()->addBulkOperation(
            'delete',
            $this->writeAlias,
            $documentMetadata->getType(),
            ['_id' => $id]
        );

        if ($this->getConnection()->isAutocommit()) {
            $this->getConnection()->commit();
        }
    }

    /**
     * Adds a document update to a bulk request for the next commit.
     *
     * @param string $documentClass The document class in short notation (i.e. AppBundle:Product)
     * @param string $id            Document id to update.
     * @param array  $fields        Fields array to update (ignored if script is specified).
     * @param string $script        Groovy script to update fields.
     * @param array  $params        Additional parameters to pass to the client.
     */
    public function update($documentClass, $id, array $fields = [], $script = null, array $params = [])
    {
        $documentMetadata = $this->metadataCollector->getDocumentMetadata($documentClass);

        $query = array_filter(array_merge(
            [
                '_id' => $id,
                'doc' => $fields,
                'script' => $script,
            ],
            $params
        ));

        $this->getConnection()->addBulkOperation(
            'update',
            $this->writeAlias,
            $documentMetadata->getType(),
            $query
        );

        if ($this->getConnection()->isAutocommit()) {
            $this->getConnection()->commit();
        }
    }

    /**
     * Adds document to a bulk request for the next commit.
     * Depending on the connection autocommit mode, the update may be committed right away.
     *
     * @param DocumentInterface $document The document entity to index in ES
     */
    public function persist(DocumentInterface $document)
    {
        if ($this->eventDispatcher) {
            $bulkOperationIndex = $this->getConnection()->getBulkOperationsCount();
            $this->eventDispatcher->dispatch(Events::PRE_PERSIST, new PrePersistEvent($document, $bulkOperationIndex));
        }

        $documentArray = $this->documentConverter->convertToArray($document);
        $this->persistRaw(get_class($document), $documentArray);
    }

    /**
     * Adds a prepared document array to a bulk request for the next commit.
     * Depending on the connection autocommit mode, the update may be committed right away.
     *
     * @param string $documentClass The document class in short notation (i.e. AppBundle:Product)
     * @param array  $documentArray The document to index in ES
     */
    public function persistRaw($documentClass, array $documentArray)
    {
        $documentMetadata = $this->metadataCollector->getDocumentMetadata($documentClass);

        $this->getConnection()->addBulkOperation(
            'index',
            $this->writeAlias,
            $documentMetadata->getType(),
            $documentArray
        );

        if ($this->getConnection()->isAutocommit()) {
            $this->getConnection()->commit();
        }
    }
}
