<?php

namespace Sineflow\ElasticsearchBundle\Manager;

use Elasticsearch\Common\Exceptions\ElasticsearchException;
use Elasticsearch\Common\Exceptions\Missing404Exception;
use Psr\Cache\InvalidArgumentException;
use Sineflow\ElasticsearchBundle\Document\DocumentInterface;
use Sineflow\ElasticsearchBundle\Document\Provider\ProviderInterface;
use Sineflow\ElasticsearchBundle\Document\Provider\ProviderRegistry;
use Sineflow\ElasticsearchBundle\Document\Repository\Repository;
use Sineflow\ElasticsearchBundle\Document\Repository\RepositoryFactory;
use Sineflow\ElasticsearchBundle\Event\Events;
use Sineflow\ElasticsearchBundle\Event\PrePersistEvent;
use Sineflow\ElasticsearchBundle\Exception\BulkRequestException;
use Sineflow\ElasticsearchBundle\Exception\Exception;
use Sineflow\ElasticsearchBundle\Exception\IndexOrAliasNotFoundException;
use Sineflow\ElasticsearchBundle\Exception\IndexRebuildingException;
use Sineflow\ElasticsearchBundle\Exception\InvalidLiveIndexException;
use Sineflow\ElasticsearchBundle\Finder\Finder;
use Sineflow\ElasticsearchBundle\Mapping\DocumentMetadata;
use Sineflow\ElasticsearchBundle\Mapping\DocumentMetadataCollector;
use Sineflow\ElasticsearchBundle\Result\DocumentConverter;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;

/**
 * Manager class.
 */
class IndexManager
{
    protected ?array $indexMapping = null;
    private array $indexSettings;
    protected Repository $repository;

    /**
     * Whether to use index aliases
     */
    protected bool $useAliases = true;

    /**
     * The alias where data should be read from
     */
    protected string $readAlias;

    /**
     * The alias where data should be written to
     */
    protected string $writeAlias;

    protected ?EventDispatcherInterface $eventDispatcher = null;

    public function __construct(
        /**
         * The unique manager name (the key from the index configuration)
         */
        protected string $managerName,
        array $indexSettings,
        protected ConnectionManager $connection,
        protected DocumentMetadataCollector $metadataCollector,
        protected ProviderRegistry $providerRegistry,
        protected Finder $finder,
        protected DocumentConverter $documentConverter,
        protected RepositoryFactory $repositoryFactory,
    ) {
        $this->useAliases = $indexSettings['use_aliases'];
        $this->indexSettings = $indexSettings;

        $this->readAlias = $this->getBaseIndexName();
        $this->writeAlias = $this->getBaseIndexName();

        if (true === $this->useAliases) {
            $this->writeAlias .= '_write';
        }
    }

    public function getEventDispatcher(): EventDispatcherInterface
    {
        return $this->eventDispatcher;
    }

    public function setEventDispatcher(EventDispatcherInterface $eventDispatcher): void
    {
        $this->eventDispatcher = $eventDispatcher;
    }

    public function getIndexMapping(): array
    {
        if (null === $this->indexMapping) {
            $this->indexMapping = $this->buildIndexMapping();
        }

        return $this->indexMapping;
    }

    /**
     * Returns mapping array for index
     */
    private function buildIndexMapping(): array
    {
        $index = ['index' => $this->indexSettings['name']];

        if (!empty($this->indexSettings['settings'])) {
            $index['body']['settings'] = $this->indexSettings['settings'];
        }

        $index['body']['mappings'] = $this->getDocumentMetadata()->getClientMapping();

        return $index;
    }

    public function getManagerName(): string
    {
        return $this->managerName;
    }

    public function getUseAliases(): bool
    {
        return $this->useAliases;
    }

    /**
     * Returns the 'read' alias when using aliases, or the index name, when not
     */
    public function getReadAlias(): string
    {
        return $this->readAlias;
    }

    /**
     * Returns the 'write' alias when using aliases, or the index name, when not
     */
    public function getWriteAlias(): string
    {
        return $this->writeAlias;
    }

    private function setWriteAlias(string $writeAlias)
    {
        $this->writeAlias = $writeAlias;
    }

    /**
     * Returns Elasticsearch connection.
     */
    public function getConnection(): ConnectionManager
    {
        return $this->connection;
    }

    /**
     * Returns repository for a document class
     */
    public function getRepository(): Repository
    {
        return $this->repositoryFactory->getRepository($this);
    }

    /**
     * Returns the data provider object for an index
     */
    public function getDataProvider(): ProviderInterface
    {
        $dataProvider = $this->providerRegistry->getCustomProviderForEntity($this->getDocumentClass());

        if (!$dataProvider) {
            $dataProvider = $this->providerRegistry->getSelfProviderForEntity($this->getDocumentClass());
        }

        return $dataProvider;
    }

    /**
     * Returns the base index name this manager is attached to.
     *
     * When using aliases, this would not represent an actual physical index.
     * getReadAlias() and getWriteAlias() should be used instead
     */
    private function getBaseIndexName(): string
    {
        return $this->indexSettings['name'];
    }

    /**
     * Return a name for a new index, which does not already exist
     */
    protected function getUniqueIndexName(?string $suffix): string
    {
        $indexName = $baseName = $this->getBaseIndexName().($suffix ?? '_'.\date('YmdHis'));

        $i = 1;
        // Keep trying other names until there is no such existing index or alias
        while ($this->getConnection()->existsIndexOrAlias(['index' => $indexName])) {
            $indexName = $baseName.'_'.$i;
            ++$i;
        }

        return $indexName;
    }

    /**
     * @throws IndexOrAliasNotFoundException
     */
    private function getIndicesForAlias(?string $alias): array
    {
        if (true === $this->getUseAliases()) {
            $aliases = $this->getConnection()->getAliases();
            $indices = \array_keys($aliases[$alias] ?? []);
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
     * @throws IndexOrAliasNotFoundException
     */
    public function getReadIndices(): array
    {
        return $this->getIndicesForAlias($this->readAlias);
    }

    /**
     * Get and verify the existence of all indices pointed by the write alias (if using aliases),
     * or the one actual index (if not using aliases)
     *
     * @throws IndexOrAliasNotFoundException
     */
    public function getWriteIndices(): array
    {
        return $this->getIndicesForAlias($this->writeAlias);
    }

    /**
     * Returns the physical index name of the live (aka "hot") index - the one both read and write aliases point to.
     * And verify that it exists
     *
     * @throws IndexOrAliasNotFoundException If there are no indices for the read or write alias
     * @throws InvalidLiveIndexException     If live index is not found or there are more than one
     */
    public function getLiveIndex(): string
    {
        $indexName = null;

        if (true === $this->getUseAliases()) {
            $aliases = $this->getConnection()->getAliases();
            $readIndices = \array_keys($aliases[$this->readAlias] ?? []);
            $writeIndices = \array_keys($aliases[$this->writeAlias] ?? []);

            if (!$readIndices) {
                throw new IndexOrAliasNotFoundException($this->readAlias, true);
            }
            if (!$writeIndices) {
                throw new IndexOrAliasNotFoundException($this->writeAlias, true);
            }

            // Get the indices pointed to by both the read and write alias
            $liveIndices = \array_intersect($readIndices, $writeIndices);

            // Make sure there is just one such index
            if (0 === \count($liveIndices)) {
                throw new InvalidLiveIndexException(\sprintf('There is no index pointed by the "%s" and "%s" aliases', $this->readAlias, $this->writeAlias));
            }
            if (\count($liveIndices) > 1) {
                throw new InvalidLiveIndexException(\sprintf('There is more than one index pointed by the "%s" and "%s" aliases', $this->readAlias, $this->writeAlias));
            }
            $indexName = \current($liveIndices);
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
    public function createIndex(): void
    {
        if (true === $this->getUseAliases()) {
            // Make sure the read and write aliases do not exist already as aliases or physical indices
            if ($this->getConnection()->existsIndexOrAlias(['index' => $this->readAlias])) {
                throw new Exception(\sprintf('Read alias "%s" already exists as an alias or an index', $this->readAlias));
            }
            if ($this->getConnection()->existsIndexOrAlias(['index' => $this->writeAlias])) {
                throw new Exception(\sprintf('Write alias "%s" already exists as an alias or an index', $this->writeAlias));
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
                throw new Exception(\sprintf('Index "%s" already exists as an alias or an index', $this->getBaseIndexName()));
            }
            $this->getConnection()->getClient()->indices()->create($settings);
        }
    }

    /**
     * Drops elasticsearch index(es).
     */
    public function dropIndex(): void
    {
        try {
            if (true === $this->getUseAliases()) {
                // Delete all physical indices aliased by the read and write aliases
                $aliasNames = $this->readAlias.','.$this->writeAlias;
                $indices = $this->getConnection()->getClient()->indices()->getAlias(['name' => $aliasNames]);
                $this->getConnection()->getClient()->indices()->delete(['index' => \implode(',', \array_keys($indices))]);
            } else {
                $this->getConnection()->getClient()->indices()->delete(['index' => $this->getBaseIndexName()]);
            }
        } catch (Missing404Exception) {
            // No physical indices exist for the index manager's aliases, or the physical index did not exist
        }
    }

    /**
     * Rebuilds ES Index and deletes the old one,
     *
     * @param bool $deleteOld             If set, the old index will be deleted upon successful rebuilding
     * @param bool $cancelExistingRebuild If set, any indices that the write alias points to (except the live one)
     *                                    will be deleted before the new build starts
     *
     * @throws ElasticsearchException
     */
    public function rebuildIndex(bool $deleteOld = false, bool $cancelExistingRebuild = false): void
    {
        try {
            if (false === $this->getUseAliases()) {
                throw new Exception(\sprintf('Index rebuilding is not supported for "%s", unless you use aliases', $this->getBaseIndexName()));
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
                $this->getConnection()->getLogger()->notice(\sprintf('Deleted old index %s', $oldIndex));
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
                $this->getConnection()->getLogger()->notice(\sprintf('Deleted incomplete index "%s"', $newIndex));
            }

            // Rethrow exception to be further handled
            throw $e;
        }
    }

    /**
     * Rebuilds the data of a document and adds it to a bulk request for the next commit.
     * Depending on the connection autocommit mode, the change may be committed right away.
     *
     * @throws InvalidArgumentException
     */
    public function reindex(string|int $id): void
    {
        $documentClass = $this->getDocumentClass();

        $dataProvider = $this->getDataProvider();
        $document = $dataProvider->getDocument($id);

        switch (true) {
            case $document instanceof DocumentInterface:
                if ($document::class !== $documentClass) {
                    throw new Exception(\sprintf('Document must be [%s], but [%s] was returned from data provider', $documentClass, $document::class));
                }
                $this->persist($document);
                break;

            case \is_array($document):
                if (!isset($document['_id'])) {
                    throw new Exception(\sprintf('The returned document array must include an "_id" field: (%s)', \serialize($document)));
                }
                if ($document['_id'] != $id) {
                    throw new Exception(\sprintf('The document id must be [%s], but "%s" was returned from data provider', $id, $document['_id']));
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
     */
    public function delete(string|int $id): void
    {
        $this->getConnection()->addBulkOperation(
            'delete',
            $this->writeAlias,
            [],
            ['_id' => $id],
        );

        if ($this->getConnection()->isAutocommit()) {
            $this->getConnection()->commit();
        }
    }

    /**
     * Adds a document update to a bulk request for the next commit.
     *
     * @param string|int $id          Document id to update.
     * @param array      $fields      Fields array to update (ignored if script is specified).
     * @param null       $script      Script to update fields.
     * @param array      $queryParams Additional params to pass with the payload (upsert, doc_as_upsert, _source, etc.)
     * @param array      $metaParams  Additional params to pass with the meta data in the bulk request (_version, _routing, etc.)
     */
    public function update(string|int $id, array $fields = [], $script = null, array $queryParams = [], array $metaParams = []): void
    {
        // Add the id of the updated document to the meta params for the bulk request
        $metaParams = \array_merge(
            $metaParams,
            [
                '_id' => $id,
            ],
        );

        $query = \array_filter(\array_merge(
            $queryParams,
            [
                'doc'    => $fields,
                'script' => $script,
            ],
        ));

        $this->getConnection()->addBulkOperation(
            'update',
            $this->writeAlias,
            $query,
            $metaParams,
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
     * @param array             $metaParams Additional params to pass with the metadata in the bulk request (_version, _routing, etc.)
     */
    public function persist(DocumentInterface $document, array $metaParams = []): void
    {
        $this->eventDispatcher?->dispatch(new PrePersistEvent($document, $this->getConnection()), Events::PRE_PERSIST);

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
    public function persistRaw(array $documentArray, array $metaParams = []): void
    {
        // Remove any read-only meta fields from array to be persisted
        unset($documentArray['_score']);

        $this->getConnection()->addBulkOperation(
            'index',
            $this->writeAlias,
            $documentArray,
            $metaParams,
        );

        if ($this->getConnection()->isAutocommit()) {
            $this->getConnection()->commit();
        }
    }

    /**
     * Created a new index with a unique name
     */
    protected function createNewIndexWithUniqueName(?string $suffix = null): string
    {
        $settings = $this->getIndexMapping();
        $newIndex = $this->getUniqueIndexName($suffix);
        $settings['index'] = $newIndex;
        $this->getConnection()->getClient()->indices()->create($settings);

        return $newIndex;
    }

    /**
     * Retrieves all documents from the index's data provider and populates them in a new index
     *
     * @param string $oldIndex This is not used here but passed in case an overriding class may need it
     */
    protected function copyDataToNewIndex(string $newIndex, string $oldIndex): void
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

            if (\is_array($document)) {
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
            ++$i;
        }

        // Save any remaining documents to ES
        $this->getConnection()->commit();

        // Recover the autocommit mode as it was
        $this->getConnection()->setAutocommit($autocommit);
    }

    /**
     * Verify index and aliases state and try to recover if state is not ok
     *
     * @param string|null $retryForException (internal) Set on recursive calls to the exception class thrown
     *
     * @return string The live (aka "hot") index name
     */
    protected function getLiveIndexPreparedForRebuilding(bool $cancelExistingRebuild, ?string $retryForException = null): string
    {
        try {
            // Make sure the index and both aliases are properly set
            $liveIndex = $this->getLiveIndex();
            $writeIndices = $this->getWriteIndices();

            // Check if write alias points to more than one index
            if (\count($writeIndices) > 1) {
                throw new IndexRebuildingException(\array_diff($writeIndices, [$liveIndex]));
            }
        } catch (IndexOrAliasNotFoundException $e) {
            // If this is a second attempt with the same exception, then we can't do anything more
            if ($e::class === $retryForException) {
                throw $e;
            }

            // It's likely that the index doesn't exist, so try to create an empty one
            $this->createIndex();

            // Now try again
            $liveIndex = $this->getLiveIndexPreparedForRebuilding($cancelExistingRebuild, $e::class);
        } catch (IndexRebuildingException $e) {
            // If we don't want to cancel the current rebuild or this is a second attempt with the same exception,
            // then we can't do anything more
            if (!$cancelExistingRebuild || ($e::class === $retryForException)) {
                throw $e;
            }

            // Delete the partial indices currently being rebuilt
            foreach ($e->getIndices() as $partialIndex) {
                $this->getConnection()->getClient()->indices()->delete(['index' => $partialIndex]);
            }

            // Now try again
            $liveIndex = $this->getLiveIndexPreparedForRebuilding($cancelExistingRebuild, $e::class);
        }

        return $liveIndex;
    }

    /**
     * Get document metadata
     *
     * @throws InvalidArgumentException
     */
    public function getDocumentMetadata(): DocumentMetadata
    {
        return $this->metadataCollector->getDocumentMetadata($this->indexSettings['class']);
    }

    /**
     * Get FQN of document class managed by this index manager
     *
     * @throws InvalidArgumentException
     */
    public function getDocumentClass(): string
    {
        return $this->getDocumentMetadata()->getClassName();
    }
}
