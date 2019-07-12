<?php

namespace Sineflow\ElasticsearchBundle\Document\Provider;

use Sineflow\ElasticsearchBundle\Manager\IndexManagerRegistry;
use Sineflow\ElasticsearchBundle\Mapping\DocumentMetadataCollector;
use Symfony\Component\DependencyInjection\ContainerAwareInterface;
use Symfony\Component\DependencyInjection\ContainerAwareTrait;

/**
 * References persistence providers for each index and type.
 */
class ProviderRegistry implements ContainerAwareInterface
{
    use ContainerAwareTrait;

    /**
     * @var DocumentMetadataCollector
     */
    private $documentMetadataCollector;

    /**
     * @var IndexManagerRegistry
     */
    private $indexManagerRegistry;

    /**
     * @var string
     */
    private $selfProviderClass;

    /**
     * @var array
     */
    private $providers = [];

    /**
     * ProviderRegistry constructor.
     *
     * @param DocumentMetadataCollector $documentMetadataCollector
     * @param IndexManagerRegistry      $indexManagerRegistry
     * @param string                    $selfProviderClass
     */
    public function __construct(
        DocumentMetadataCollector $documentMetadataCollector,
        IndexManagerRegistry $indexManagerRegistry,
        string $selfProviderClass
    ) {
        $this->documentMetadataCollector = $documentMetadataCollector;
        $this->indexManagerRegistry = $indexManagerRegistry;
        $this->selfProviderClass = $selfProviderClass;
    }


    /**
     * Registers a provider service for the specified type entity.
     *
     * @param string $documentClass The FQN or alias to the type entity
     * @param string $providerId    The provider service id
     */
    public function addProvider(string $documentClass, string $providerId) : void
    {
        $this->providers[$this->documentMetadataCollector->getDocumentMetadata($documentClass)->getClassName()] = $providerId;
    }

    /**
     * Unsets registered provider for the specified type entity.
     *
     * @param string $documentClass The FQN or alias to the type entity
     */
    public function removeProvider(string $documentClass) : void
    {
        unset($this->providers[$this->documentMetadataCollector->getDocumentMetadata($documentClass)->getClassName()]);
    }

    /**
     * Gets registered provider service id for the specified type entity.
     *
     * @param string $documentClass The FQN or alias to the type entity
     *
     * @return string|null
     */
    public function getProviderId(string $documentClass) : ?string
    {
        $fullClassName = $this->documentMetadataCollector->getDocumentMetadata($documentClass)->getClassName();

        return isset($this->providers[$fullClassName]) ? $this->providers[$fullClassName] : null;
    }

    /**
     * Gets the provider for a type.
     *
     * @param string $documentClass FQN or alias (e.g AppBundle:Entity)
     *
     * @return ProviderInterface
     *
     * @throws \InvalidArgumentException if no provider was registered for the type
     */
    public function getProviderInstance(string $documentClass) : ProviderInterface
    {
        $documentClass = $this->documentMetadataCollector->getDocumentMetadata($documentClass)->getClassName();

        if (isset($this->providers[$documentClass])) {
            $provider = $this->container->get($this->providers[$documentClass]);
            if (!$provider instanceof ProviderInterface) {
                throw new \InvalidArgumentException(sprintf('Registered provider [%s] must implement [%s].', $this->providers[$documentClass], ProviderInterface::class));
            }

            return $provider;
        }

        // Return default self-provider, if no specific one was registered
        if (class_exists($this->selfProviderClass)) {
            $indexManager = $this->indexManagerRegistry->get(
                $this->documentMetadataCollector->getDocumentClassIndex($documentClass)
            );

            return new $this->selfProviderClass(
                $documentClass,
                $this->documentMetadataCollector,
                $indexManager,
                $documentClass
            );
        }

        throw new \InvalidArgumentException(sprintf('No data provider is registered for [%s].', $documentClass));
    }
}
