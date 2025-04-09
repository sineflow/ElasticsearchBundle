<?php

namespace Sineflow\ElasticsearchBundle\Manager;

use Sineflow\ElasticsearchBundle\Exception\InvalidConnectionManagerException;
use Symfony\Component\DependencyInjection\ServiceLocator;

/**
 * Class to get defined connection services
 */
class ConnectionManagerRegistry
{
    public function __construct(
        private readonly ServiceLocator $serviceLocator,
    ) {
    }

    /**
     * Returns the connection service for a given connection name
     *
     * @param string $name The connection name from the 'sineflow_elasticsearch.connections' configuration
     *
     * @throws InvalidConnectionManagerException If service does not exist or is the wrong class
     */
    public function get(string $name): ConnectionManager
    {
        $serviceId = \sprintf('sfes.connection.%s', $name);
        if ($this->serviceLocator->has($serviceId)) {
            $connectionManager = $this->serviceLocator->get($serviceId);
            if (!$connectionManager instanceof ConnectionManager) {
                throw new \RuntimeException(\sprintf('The service "%s" must be instance of "%s".', $serviceId, ConnectionManager::class));
            }

            return $connectionManager;
        }

        throw new InvalidConnectionManagerException(\sprintf('Connection service "%s" is not found. Available ones are %s', $serviceId, \implode(', ', \array_keys($this->serviceLocator->getProvidedServices()))));
    }

    /**
     * Get all connection instances defined
     *
     * @return \Generator<ConnectionManager>
     */
    public function getAll(): iterable
    {
        foreach ($this->serviceLocator->getProvidedServices() as $serviceId => $serviceClass) {
            yield $this->serviceLocator->get($serviceId);
        }
    }
}
