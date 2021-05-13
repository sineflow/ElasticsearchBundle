<?php

namespace Sineflow\ElasticsearchBundle\Manager;

use Sineflow\ElasticsearchBundle\Document\DocumentInterface;
use Sineflow\ElasticsearchBundle\Exception\InvalidIndexManagerException;
use Sineflow\ElasticsearchBundle\Mapping\DocumentMetadataCollector;
use Symfony\Component\DependencyInjection\ContainerAwareTrait;

/**
 * Class to get defined index manager services
 */
class IndexManagerRegistry
{
    use ContainerAwareTrait;

    /**
     * @var DocumentMetadataCollector
     */
    private $metadataCollector;

    /**
     * @var iterable<IndexManager>
     */
    private $indexManagers;

    /**
     * Constructor
     *
     * @param DocumentMetadataCollector $metadataCollector
     * @param iterable                  $indexManagers
     */
    public function __construct(DocumentMetadataCollector $metadataCollector, iterable $indexManagers)
    {
        $this->metadataCollector = $metadataCollector;
        $this->indexManagers = $indexManagers;
    }

    /**
     * Returns the index manager service for a given index manager name
     *
     * @param string $name
     *
     * @return IndexManager
     *
     * @throws InvalidIndexManagerException If service does not exist or is the wrong class
     */
    public function get(string $name): IndexManager
    {
        foreach ($this->indexManagers as $indexManager) {
            if ($indexManager->getManagerName() === $name) {
                return $indexManager;
            }
        }

        throw new InvalidIndexManagerException(sprintf('No manager is defined for [%s] index', $name));
    }

    /**
     * Returns the index manager managing a given Elasticsearch entity
     *
     * @param DocumentInterface $entity
     *
     * @return IndexManager
     */
    public function getByEntity(DocumentInterface $entity): IndexManager
    {
        $indexManagerName = $this->metadataCollector->getDocumentClassIndex(get_class($entity));

        return $this->get($indexManagerName);
    }

    /**
     * Get all index manager instances defined
     *
     * @return iterable<IndexManager>
     */
    public function getAll(): iterable
    {
        return $this->indexManagers;
    }
}
