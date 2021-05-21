<?php

namespace Sineflow\ElasticsearchBundle\Manager;

use Sineflow\ElasticsearchBundle\Document\DocumentInterface;
use Sineflow\ElasticsearchBundle\Exception\InvalidIndexManagerException;
use Sineflow\ElasticsearchBundle\Mapping\DocumentMetadataCollector;
use Symfony\Component\DependencyInjection\ServiceLocator;

/**
 * Class to get defined index manager services
 */
class IndexManagerRegistry
{
    /**
     * @var DocumentMetadataCollector
     */
    private $metadataCollector;

    /**
     * @var ServiceLocator
     */
    private $serviceLocator;

    /**
     * Constructor
     *
     * @param DocumentMetadataCollector $metadataCollector
     * @param ServiceLocator            $serviceLocator
     */
    public function __construct(DocumentMetadataCollector $metadataCollector, ServiceLocator $serviceLocator)
    {
        $this->metadataCollector = $metadataCollector;
        $this->serviceLocator = $serviceLocator;
    }

    /**
     * Returns the index manager service for a given index manager name
     *
     * @param string $name The index manager name from the 'sineflow_elasticsearch.indices' configuration
     *
     * @return IndexManager
     *
     * @throws InvalidIndexManagerException If service does not exist or is the wrong class
     */
    public function get(string $name): IndexManager
    {
        $serviceId = sprintf('sfes.index.%s', $name);
        if ($this->serviceLocator->has($serviceId)) {
            $indexManager = $this->serviceLocator->get($serviceId);
            if (! $indexManager instanceof IndexManager) {
                throw new \RuntimeException(sprintf('The service "%s" must be instance of "%s".', $serviceId, IndexManager::class));
            }

            return $indexManager;
        }

        throw new InvalidIndexManagerException(sprintf('Index manager service "%s" is not found. Available ones are %s', $serviceId, implode(', ', array_keys($this->serviceLocator->getProvidedServices()))));
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
     * @return \Generator<IndexManager>
     */
    public function getAll(): iterable
    {
        foreach ($this->serviceLocator->getProvidedServices() as $serviceId => $serviceClass) {
            yield $this->serviceLocator->get($serviceId);
        }
    }
}
