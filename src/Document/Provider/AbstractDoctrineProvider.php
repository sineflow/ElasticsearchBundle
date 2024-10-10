<?php

namespace Sineflow\ElasticsearchBundle\Document\Provider;

use Doctrine\ORM\AbstractQuery;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Query;
use Sineflow\ElasticsearchBundle\Document\DocumentInterface;

/**
 * Base doctrine document provider
 */
abstract class AbstractDoctrineProvider extends AbstractProvider
{
    protected Query $query;

    /**
     * How many records to retrieve from DB at once
     */
    protected int $batchSize = 1000;

    /**
     * The Doctrine entity name
     */
    protected string $doctrineEntityName;

    /**
     * How to hydrate doctrine results
     *
     * @phpstan-param AbstractQuery::HYDRATE_* $sourceDataHydration
     */
    protected int|string $sourceDataHydration = AbstractQuery::HYDRATE_OBJECT;

    /**
     * @param string                 $documentClass The document class the provider is for
     * @param EntityManagerInterface $em            The Doctrine entity manager
     */
    public function __construct(protected string $documentClass, protected EntityManagerInterface $em)
    {
    }

    public function setBatchSize(int $batchSize): void
    {
        $this->batchSize = $batchSize;
    }

    /**
     * Gets the query that will return all records from the DB
     */
    abstract public function getQuery(): Query;

    /**
     * Converts a Doctrine entity to Elasticsearch entity
     *
     * @param object|array $entity A doctrine entity object or data array
     *
     * @return DocumentInterface|array An ES document entity object or document array
     */
    abstract protected function getAsDocument(object|array $entity): DocumentInterface|array;

    /**
     * Returns a PHP Generator for iterating over the full dataset of source data that is to be inserted in ES
     * The returned data can be either a document entity or an array ready for direct sending to ES
     *
     * @return \Generator<DocumentInterface|array>
     */
    public function getDocuments(): \Generator
    {
        \set_time_limit(3600);

        $query = $this->getQuery();

        $offset = 0;
        $query->setMaxResults($this->batchSize);
        do {
            // Get a batch of records
            $query->setFirstResult($offset);

            $records = $query->getResult($this->sourceDataHydration);

            $this->em->clear();
            \gc_collect_cycles();

            // Convert each to an ES entity and return it
            foreach ($records as $record) {
                $document = $this->getAsDocument($record);

                yield $document;
            }

            $offset += $this->batchSize;
        } while (!empty($records));
    }

    /**
     * Build and return a document entity from the data source
     * The returned data can be either a document entity or an array ready for direct sending to ES
     */
    public function getDocument(int|string $id): DocumentInterface|array|null
    {
        $entity = $this->em->getRepository($this->doctrineEntityName)->find($id);

        return $this->getAsDocument($entity);
    }
}
