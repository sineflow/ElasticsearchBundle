<?php

namespace Sineflow\ElasticsearchBundle\Document\Provider;

use Symfony\Component\DependencyInjection\ContainerAwareInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * References persistence providers for each index and type.
 */
class ProviderRegistry implements ContainerAwareInterface
{
    /**
     * @var ContainerInterface
     */
    private $container;

    private $providers = array();

    /**
     * Sets the Container.
     *
     * @param ContainerInterface|null $container A ContainerInterface instance or null
     */
    public function setContainer(ContainerInterface $container = null)
    {
        $this->container = $container;
    }

    /**
     * Registers a provider service for the specified type entity.
     *
     * @param string $documentClass The short path to the type entity (e.g AppBundle:MyType)
     * @param string $providerId    The provider service id
     */
    public function addProvider($documentClass, $providerId)
    {
        $this->providers[$documentClass] = $providerId;
    }

    /**
     * Unsets registered provider for the specified type entity.
     *
     * @param string $documentClass The short path to the type entity (e.g AppBundle:MyType)
     */
    public function removeProvider($documentClass)
    {
        unset($this->providers[$documentClass]);
    }

    /**
     * Gets registered provider service id for the specified type entity.
     *
     * @param string $documentClass The short path to the type entity (e.g AppBundle:MyType)
     * @return string|null
     */
    public function getProviderId($documentClass)
    {
        return isset($this->providers[$documentClass]) ? $this->providers[$documentClass] : null;
    }

    /**
     * Gets the provider for a type.
     *
     * @param string $documentClass The short path to the type entity (e.g AppBundle:MyType)
     * @return ProviderInterface
     * @throws \InvalidArgumentException if no provider was registered for the type
     */
    public function getProviderInstance($documentClass)
    {
        if (isset($this->providers[$documentClass])) {
            $provider = $this->container->get($this->providers[$documentClass]);
            if (!$provider instanceof ProviderInterface) {
                throw new \InvalidArgumentException(sprintf('Registered provider "%s" must implement ProviderInterface.', $this->providers[$documentClass]));
            }

            return $provider;
        }

        // Return default self-provider, if no specific one was registered
        $providerClass = $this->container->getParameter('sfes.provider_self.class');
        if (class_exists($providerClass)) {
            $indexManager = $this->container->get('sfes.index_manager_registry')->get(
                $this->container->get('sfes.document_metadata_collector')->getDocumentClassIndex($documentClass)
            );

            return new $providerClass(
                $documentClass,
                $this->container->get('sfes.document_metadata_collector'),
                $indexManager,
                $documentClass
            );
        }

        throw new \InvalidArgumentException(sprintf('No provider is registered for type "%s".', $documentClass));
    }
}
