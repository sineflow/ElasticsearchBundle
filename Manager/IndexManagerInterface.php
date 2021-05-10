<?php

namespace Sineflow\ElasticsearchBundle\Manager;

use Sineflow\ElasticsearchBundle\Document\Provider\ProviderInterface;
use Sineflow\ElasticsearchBundle\Document\Repository\Repository;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;

interface IndexManagerInterface
{
    /**
     * @return EventDispatcherInterface
     */
    public function getEventDispatcher(): EventDispatcherInterface;

    /**
     * @param EventDispatcherInterface $eventDispatcher
     */
    public function setEventDispatcher(EventDispatcherInterface $eventDispatcher);

    /**
     * @return array
     */
    public function getIndexMapping(): array;

    /**
     * @return string
     */
    public function getManagerName(): string;

    /**
     * @return bool
     */
    public function getUseAliases(): bool;

    /**
     * Returns the 'read' alias when using aliases, or the index name, when not
     *
     * @return string
     */
    public function getReadAlias(): string;

    /**
     * Returns the 'write' alias when using aliases, or the index name, when not
     *
     * @return string
     */
    public function getWriteAlias(): string;

    /**
     * Returns Elasticsearch connection.
     *
     * @return ConnectionManager
     */
    public function getConnection(): ConnectionManager;

    /**
     * Returns repository for a document class
     *
     * @return Repository
     */
    public function getRepository(): Repository;

    /**
     * Returns the data provider object for an index
     *
     * @return ProviderInterface
     */
    public function getDataProvider(): ProviderInterface;

    /**
     * Get FQN of document class managed by this index manager
     *
     * @return string
     */
    public function getDocumentClass(): string;
}
