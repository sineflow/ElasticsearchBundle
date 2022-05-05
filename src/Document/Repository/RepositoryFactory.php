<?php

namespace Sineflow\ElasticsearchBundle\Document\Repository;

use Sineflow\ElasticsearchBundle\Finder\Finder;
use Sineflow\ElasticsearchBundle\Manager\IndexManager;
use Sineflow\ElasticsearchBundle\Mapping\DocumentMetadata;
use Symfony\Component\DependencyInjection\ServiceLocator;

/**
 * Gets repositories from the container or falls back to normal creation.
 */
class RepositoryFactory
{
    /**
     * @var ServiceLocator
     */
    private $container;

    /**
     * @var Finder
     */
    private $finder;

    /**
     * @var Repository[]
     */
    private $knownRepositories = [];

    /**
     * RepositoryFactory constructor.
     */
    public function __construct(ServiceLocator $container, Finder $finder)
    {
        $this->container = $container;
        $this->finder = $finder;
    }

    public function getRepository(IndexManager $indexManager): Repository
    {
        $documentMetadata = $indexManager->getDocumentMetadata();
        $customRepositoryClass = $documentMetadata->getRepositoryClass();

        // If there is a custom repository specified for the entity
        if (null !== $customRepositoryClass) {
            // Try to get from the service container, in case there is such service available
            if ($this->container->has($customRepositoryClass)) {
                $repository = $this->container->get($customRepositoryClass);
                if (!$repository instanceof Repository) {
                    throw new \RuntimeException(\sprintf('The service "%s" must extend "%s".', $customRepositoryClass, Repository::class));
                }

                return $repository;
            }

            // If not in the container but the class implements the interface for a repository service
            if (\is_a($customRepositoryClass, ServiceRepositoryInterface::class, true)) {
                throw new \RuntimeException(\sprintf('The "%s" repository implements "%s", but its service could not be found. Make sure the service exists and is tagged with "sfes.repository".', $customRepositoryClass, ServiceRepositoryInterface::class));
            }

            // If the repository class specified doesn't exist at all
            if (!\class_exists($customRepositoryClass)) {
                throw new \RuntimeException(\sprintf('The "%s" entity has a repositoryClass set to "%s", but this is not a valid class. Check your class naming. If this is meant to be a service id, make sure this service exists and is tagged with "sfes.repository".', $documentMetadata->getClassName(), $customRepositoryClass));
            }

            // the specified repository class is apparently not a service...
        }

        // if we already have the repository instance for this IndexManager from before, return it straight away
        $repositoryHash = $documentMetadata->getClassName().\spl_object_hash($indexManager);
        if (isset($this->knownRepositories[$repositoryHash])) {
            return $this->knownRepositories[$repositoryHash];
        }

        // create a new Repository instance
        return $this->knownRepositories[$repositoryHash] = $this->createRepository($indexManager, $documentMetadata);
    }

    private function createRepository(IndexManager $indexManager, DocumentMetadata $documentMetadata): Repository
    {
        $repositoryClass = $documentMetadata->getRepositoryClass() ?: Repository::class;
        $repository = new $repositoryClass($indexManager, $this->finder);

        return $repository;
    }
}
