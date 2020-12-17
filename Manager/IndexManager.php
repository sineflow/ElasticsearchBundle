<?php

namespace Sineflow\ElasticsearchBundle\Manager;

use Elasticsearch\Common\Exceptions\ElasticsearchException;
use Elasticsearch\Common\Exceptions\Missing404Exception;
use Sineflow\ElasticsearchBundle\Document\DocumentInterface;
use Sineflow\ElasticsearchBundle\Document\Provider\ProviderInterface;
use Sineflow\ElasticsearchBundle\Document\Provider\ProviderRegistry;
use Sineflow\ElasticsearchBundle\Document\Repository\Repository;
use Sineflow\ElasticsearchBundle\Event\Events;
use Sineflow\ElasticsearchBundle\Event\PrePersistEvent;
use Sineflow\ElasticsearchBundle\Exception\BulkRequestException;
use Sineflow\ElasticsearchBundle\Exception\Exception;
use Sineflow\ElasticsearchBundle\Exception\IndexOrAliasNotFoundException;
use Sineflow\ElasticsearchBundle\Exception\IndexRebuildingException;
use Sineflow\ElasticsearchBundle\Exception\InvalidLiveIndexException;
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
    protected $indexMapping = null;

    /**
     * @var array
     */
    private $indexSettings;

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
        $this->indexSettings = $indexSettings;

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
     * @return array
     */
    public function getIndexMapping()
    {
        if (is_null($this->indexMapping)) {
            $this->indexMapping = $this->buildIndexMapping($this->managerName);
        }

        return $this->indexMapping;
    }

    /**
     * Returns mapping array for index
     *
     * @param string $indexManagerName
     *
     * @return array
     */
    private function buildIndexMapping($indexManagerName)
    {
        $index = ['index' => $this->indexSettings['name']];

        if (!empty($this->indexSettings['settings'])) {
            $index['body']['settings'] = $this->indexSettings['settings'];
        }

        $metadata = $this->metadataCollector->getDocumentMetadataForIndex($indexManagerName);
        $index['body']['mappings'][$metadata->getType()] = $metadata->getClientMapping();

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
     * @return Repository
     */
    public function getRepository()
    {
        $documentMetadata = $this->metadataCollector->getDocumentMetadataForIndex($this->managerName);

        if (isset($this->repositories[$documentMetadata->getClassName()])) {
            return $this->repositories[$documentMetadata->getClassName()];
        }

        $repositoryClass = $documentMetadata->getRepositoryClass() ?: Repository::class;
        $repo = new $repositoryClass($this, $documentMetadata->getClassName(), $this->finder, $this->metadataCollector);

        if (!($repo instanceof Repository)) {
            throw new \InvalidArgumentException(sprintf('Repository [%s] must extend [%s]', $repositoryClass, Repository::class));
        }
        $this->repositories[$documentMetadata->getClassName()] = $repo;

        return $repo;
    }

    /**
     * Returns the data provider object for a type (provided in short class notation, e.g AppBundle:Product)
     *
     * @return ProviderInterface
     */
    public function getDataProvider()
    {
        $provider = $this->providerRegistry->getProviderInstance(
            $this->getDocumentClass()
        );

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
        return $this->indexSettings['name'];
    }

    /**
     * Return a name for a new index, which does not already exist
     *
     * @return string
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
     * @param string $alias
     *
     * @return array
     *
     * @throws IndexOrAliasNotFoundException
     */
    private function getIndicesForAlias(?string $alias)
    {
        if (true === $this->getUseAliases()) {
            $aliases = $this->getConnection()->getAliases();
            $indices = array_keys($aliases[$alias] ?? []);
            if (!$indices) {
                throw new IndexOrAliasNotFoundException($alias, true);
            }
        } else {
            $indexName = $this->getBaseIndexName();
            if (!$this->getConnection()->existsIndexOrAlias(['index' => $indexName])) {
                throw new IndexOrAliasNotFoundException($indexName);
            }
            $indices = [$this->getBaseIndexName()];
        }

        return $indices;
    }

    /**
     * Get and verify the existence of all indices pointed by the read alias (if using aliases),
     * or the one actual index (if not using aliases)
     *
     * @return array
     *
     * @throws IndexOrAliasNotFoundException
     */
    public function getReadIndices()
    {
        return $this->getIndicesForAlias($this->readAlias);
    }

    /**
     * Get and verify the existence of all indices pointed by the write alias (if using aliases),
     * or the one actual index (if not using aliases)
     *
     * @return array
     *
     * @throws IndexOrAliasNotFoundException
     */
    public function getWriteIndices()
    {
        return $this->getIndicesForAlias($this->writeAlias);
    }

    /**
     * Returns the physical index name of the live (aka "hot") index - the one both read and write aliases point to.
     * And verify that it exists
     *
     * @return string
     *
     * @throws IndexOrAliasNotFoundException If there are no indices for the read or write alias
     * @throws InvalidLiveIndexException     If live index is not found or there are more than one
     */
    public function getLiveIndex()
    {
        $indexName = null;

        if (true === $this->getUseAliases()) {
            $aliases = $this->getConnection()->getAliases();
            $readIndices = array_keys($aliases[$this->readAlias] ?? []);
            $writeIndices = array_keys($aliases[$this->writeAlias] ?? []);

            if (!$readIndices) {
                throw new IndexOrAliasNotFoundException($this->readAlias, true);
            }
            if (!$writeIndices) {
                throw new IndexOrAliasNotFoundException($this->writeAlias, true);
            }

            // Get the indices pointed to by both the read and write alias
            $liveIndices = array_intersect($readIndices, $writeIndices);

            // Make sure there is just one such index
            if (count($liveIndices) === 0) {
                throw new InvalidLiveIndexException(sprintf('There is no index pointed by the "%s" and "%s" aliases', $this->readAlias, $this->writeAlias));
            }
            if (count($liveIndices) > 1) {
                throw new InvalidLiveIndexException(sprintf('There is more than one index pointed by the "%s" and "%s" aliases', $this->readAlias, $this->writeAlias));
            }
            $indexName = current($liveIndices);
        } else {
            $indexName = $this->getIndicesForAlias(null)[0];
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
        if (true === $this->getUseAliases()) {
            // Make sure the read and write aliases do not exist already as aliases or physical indices
            if ($this->getConnection()->existsIndexOrAlias(array('index' => $this->readAlias))) {
                throw new Exception(sprintf('Read alias "%s" already exists as an alias or an index', $this->readAlias));
            }
            if ($this->getConnection()->existsIndexOrAlias(array('index' => $this->writeAlias))) {
                throw new Exception(sprintf('Write alias "%s" already exists as an alias or an index', $this->writeAlias));
            }

            // Create physical index with a unique name
            $newIndex = $this->createNewIndexWithUniqueName();

            // Set aliases to index
            $setAliasParams = [
                'body' => [
                    'actions' => [
                        ['add' => ['index' => $newIndex, 'alias' => $this->readAlias]],
                        ['add' => ['index' => $newIndex, 'alias' => $this->writeAlias]],
                    ],
                ],
            ];
            $this->getConnection()->getClient()->indices()->updateAliases($setAliasParams);
        } else {
            $settings = $this->getIndexMapping();
            // Make sure the index name does not exist already as a physical index or alias
            if ($this->getConnection()->existsIndexOrAlias(['index' => $this->getBaseIndexName()])) {
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
        try {
            if (false === $this->getUseAliases()) {
                throw new Exception('Index rebuilding is not supported, unless you use aliases');
            }

            $oldIndex = $this->getLiveIndexPreparedForRebuilding($cancelExistingRebuild);
            $newIndex = $this->createNewIndexWithUniqueName();

            // Point write alias to the new index as well
            $setAliasParams = [
                'body' => [
                    'actions' => [
                        ['add' => ['index' => $newIndex, 'alias' => $this->writeAlias]],
                    ],
                ],
            ];
            $this->getConnection()->getClient()->indices()->updateAliases($setAliasParams);

            $this->copyDataToNewIndex($newIndex, $oldIndex);

            // Point both aliases to the new index and remove them from the old
            $setAliasParams = [
                'body' => [
                    'actions' => [
                        ['add' => ['index' => $newIndex, 'alias' => $this->readAlias]],
                        ['remove' => ['index' => $oldIndex, 'alias' => $this->readAlias]],
                        ['remove' => ['index' => $oldIndex, 'alias' => $this->writeAlias]],
                    ],
                ],
            ];
            $this->getConnection()->getClient()->indices()->updateAliases($setAliasParams);

            // Delete the old index
            if ($deleteOld) {
                $this->getConnection()->getClient()->indices()->delete(['index' => $oldIndex]);
                $this->getConnection()->getLogger()->notice(sprintf('Deleted old index %s', $oldIndex));
            }
        } catch (\Exception $e) {
            // Do not log BulkRequestException here as they are logged in the connection manager
            // Do not log ElasticsearchException either, as they are logged inside the elasticsearch bundle
            if (!($e instanceof BulkRequestException) && !($e instanceof ElasticsearchException)) {
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
     * Rebuilds the data of a document and adds it to a bulk request for the next commit.
     * Depending on the connection autocommit mode, the change may be committed right away.
     *
     * @param string|int $id
     */
    public function reindex($id)
    {
        $documentClass = $this->getDocumentClass();

        $dataProvider = $this->getDataProvider();
        $document = $dataProvider->getDocument($id);

        switch (true) {
            case $document instanceof DocumentInterface:
                if (get_class($document) !== $documentClass) {
                    throw new Exception(sprintf('Document must be [%s], but [%s] was returned from data provider', $documentClass, get_class($document)));
                }
                $this->persist($document);
                break;

            case is_array($document):
                if (!isset($document['_id'])) {
                    throw new Exception(sprintf('The returned document array must include an "_id" field: (%s)', serialize($document)));
                }
                if ($document['_id'] != $id) {
                    throw new Exception(sprintf('The document id must be [%s], but "%s" was returned from data provider', $id, $document['_id']));
                }
                $this->persistRaw($document);
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
     * @param string $id Document ID to remove.
     */
    public function delete($id)
    {
        $documentMetadata = $this->metadataCollector->getDocumentMetadata($this->indexSettings['class']);

        $this->getConnection()->addBulkOperation(
            'delete',
            $this->writeAlias,
            $documentMetadata->getType(),
            [],
            ['_id' => $id]
        );

        if ($this->getConnection()->isAutocommit()) {
            $this->getConnection()->commit();
        }
    }

    /**
     * Adds a document update to a bulk request for the next commit.
     *
     * @param string $id          Document id to update.
     * @param array  $fields      Fields array to update (ignored if script is specified).
     * @param string $script      Script to update fields.
     * @param array  $queryParams Additional params to pass with the payload (upsert, doc_as_upsert, _source, etc.)
     * @param array  $metaParams  Additional params to pass with the meta data in the bulk request (_version, _routing, etc.)
     */
    public function update($id, array $fields = [], $script = null, array $queryParams = [], array $metaParams = [])
    {
        $documentMetadata = $this->metadataCollector->getDocumentMetadata($this->indexSettings['class']);

        // Add the id of the updated document to the meta params for the bulk request
        $metaParams = array_merge(
            $metaParams,
            [
                '_id' => $id,
            ]
        );

        $query = array_filter(array_merge(
            $queryParams,
            [
                'doc' => $fields,
                'script' => $script,
            ]
        ));

        $this->getConnection()->addBulkOperation(
            'update',
            $this->writeAlias,
            $documentMetadata->getType(),
            $query,
            $metaParams
        );

        if ($this->getConnection()->isAutocommit()) {
            $this->getConnection()->commit();
        }
    }

    /**
     * Adds document to a bulk request for the next commit.
     * Depending on the connection autocommit mode, the update may be committed right away.
     *
     * @param DocumentInterface $document   The document entity to index in ES
     * @param array             $metaParams Additional params to pass with the meta data in the bulk request (_version, _routing, etc.)
     */
    public function persist(DocumentInterface $document, array $metaParams = [])
    {
        if ($this->eventDispatcher) {
            $this->eventDispatcher->dispatch(Events::PRE_PERSIST, new PrePersistEvent($document, $this->getConnection()));
        }

        $documentArray = $this->documentConverter->convertToArray($document);
        $this->persistRaw($documentArray, $metaParams);
    }

    /**
     * Adds a prepared document array to a bulk request for the next commit.
     * Depending on the connection autocommit mode, the update may be committed right away.
     *
     * @param array $documentArray The document to index in ES
     * @param array $metaParams    Additional params to pass with the meta data in the bulk request (_version, _routing, etc.)
     */
    public function persistRaw(array $documentArray, array $metaParams = [])
    {
        $documentMetadata = $this->metadataCollector->getDocumentMetadata($this->indexSettings['class']);

        // Remove any read-only meta fields from array to be persisted
        unset($documentArray['_score']);

        $this->getConnection()->addBulkOperation(
            'index',
            $this->writeAlias,
            $documentMetadata->getType(),
            $documentArray,
            $metaParams
        );

        if ($this->getConnection()->isAutocommit()) {
            $this->getConnection()->commit();
        }
    }

    /**
     * Created a new index with a unique name
     *
     * @return string
     */
    protected function createNewIndexWithUniqueName()
    {
        $settings = $this->getIndexMapping();
        $newIndex = $this->getUniqueIndexName();
        $settings['index'] = $newIndex;
        $this->getConnection()->getClient()->indices()->create($settings);

        return $newIndex;
    }

    /**
     * Retrieves all documents from the index's data provider and populates them in a new index
     *
     * @param string $newIndex
     * @param string $oldIndex This is not used here but passed in case an overriding class may need it
     */
    protected function copyDataToNewIndex(string $newIndex, string $oldIndex)
    {
        // Make sure we don't autocommit on every item in the bulk request
        $autocommit = $this->getConnection()->isAutocommit();
        $this->getConnection()->setAutocommit(false);

        $indexDataProvider = $this->getDataProvider();
        $batchSize = $indexDataProvider->getPersistRequestBatchSize() ?? $this->connection->getConnectionSettings()['bulk_batch_size'];

        $i = 1;
        foreach ($indexDataProvider->getDocuments() as $document) {
            // Temporarily override the write alias with the new physical index name, so rebuilding only happens in the new index
            $originalWriteAlias = $this->writeAlias;
            $this->setWriteAlias($newIndex);

            if (is_array($document)) {
                $this->persistRaw($document);
            } else {
                $this->persist($document);
            }

            // Restore write alias name
            $this->setWriteAlias($originalWriteAlias);

            // Send the bulk request every X documents, so it doesn't get too big
            if (0 === $i % $batchSize) {
                $this->getConnection()->commit();
            }
            $i++;
        }

        // Save any remaining documents to ES
        $this->getConnection()->commit();

        // Recover the autocommit mode as it was
        $this->getConnection()->setAutocommit($autocommit);
    }

    /**
     * Verify index and aliases state and try to recover if state is not ok
     *
     * @param bool   $cancelExistingRebuild
     * @param string $retryForException     (internal) Set on recursive calls to the exception class thrown
     *
     * @return string The live (aka "hot") index name
     */
    protected function getLiveIndexPreparedForRebuilding($cancelExistingRebuild, $retryForException = null)
    {
        try {
            // Make sure the index and both aliases are properly set
            $liveIndex = $this->getLiveIndex();
            $writeIndices = $this->getWriteIndices();

            // Check if write alias points to more than one index
            if (count($writeIndices) > 1) {
                throw new IndexRebuildingException(array_diff($writeIndices, [$liveIndex]));
            }
        } catch (IndexOrAliasNotFoundException $e) {
            // If this is a second attempt with the same exception, then we can't do anything more
            if (get_class($e) === $retryForException) {
                throw $e;
            }

            // It's likely that the index doesn't exist, so try to create an empty one
            $this->createIndex();

            // Now try again
            $liveIndex = $this->getLiveIndexPreparedForRebuilding($cancelExistingRebuild, get_class($e));
        } catch (IndexRebuildingException $e) {
            // If we don't want to cancel the current rebuild or this is a second attempt with the same exception,
            // then we can't do anything more
            if (!$cancelExistingRebuild || (get_class($e) === $retryForException)) {
                throw $e;
            }

            // Delete the partial indices currently being rebuilt
            foreach ($e->getIndices() as $partialIndex) {
                $this->getConnection()->getClient()->indices()->delete(['index' => $partialIndex]);
            }

            // Now try again
            $liveIndex = $this->getLiveIndexPreparedForRebuilding($cancelExistingRebuild, get_class($e));
        }

        return $liveIndex;
    }

    /**
     * Get FQN of document class managed by this index manager
     *
     * @return string
     */
    protected function getDocumentClass()
    {
        return $this->metadataCollector->getDocumentMetadata($this->indexSettings['class'])->getClassName();
    }
}
