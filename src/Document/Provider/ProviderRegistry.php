<?php

namespace Sineflow\ElasticsearchBundle\Document\Provider;

use Sineflow\ElasticsearchBundle\Manager\IndexManagerRegistry;
use Sineflow\ElasticsearchBundle\Mapping\DocumentMetadataCollector;
use Symfony\Component\DependencyInjection\ServiceLocator;

/**
 * References persistence providers for each index.
 */
class ProviderRegistry
{
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
     * @var ServiceLocator
     */
    private $serviceLocator;

    /**
     * ProviderRegistry constructor.
     */
    public function __construct(
        ServiceLocator $serviceLocator,
        DocumentMetadataCollector $documentMetadataCollector,
        IndexManagerRegistry $indexManagerRegistry,
        string $selfProviderClass
    ) {
        $this->serviceLocator = $serviceLocator;
        $this->documentMetadataCollector = $documentMetadataCollector;
        $this->indexManagerRegistry = $indexManagerRegistry;
        $this->selfProviderClass = $selfProviderClass;
    }

    public function getCustomProviderForEntity(string $documentClass): ?ProviderInterface
    {
        $documentMetadata = $this->documentMetadataCollector->getDocumentMetadata($documentClass);
        $customProviderClass = $documentMetadata->getProviderClass();

        // If there is a custom provider specified for the entity
        if (null !== $customProviderClass) {
            if ($this->serviceLocator->has($customProviderClass)) {
                $provider = $this->serviceLocator->get($customProviderClass);
                if (!$provider instanceof ProviderInterface) {
                    throw new \RuntimeException(\sprintf('The service "%s" must implement "%s".', $customProviderClass, ProviderInterface::class));
                }

                return $provider;
            }

            throw new \InvalidArgumentException(\sprintf('Provider service "%s" was not found. Make sure the service exists and is tagged with "sfes.provider".', $customProviderClass));
        }

        return null;
    }

    /**
     * Returns the self-provider if available (the provider that allows the index to rebuild from itself)
     */
    public function getSelfProviderForEntity(string $documentClass): ?ProviderInterface
    {
        if (!\class_exists($this->selfProviderClass)) {
            return null;
        }

        $indexManager = $this->indexManagerRegistry->get(
            $this->documentMetadataCollector->getDocumentClassIndex($documentClass)
        );

        return new $this->selfProviderClass(
            $this->documentMetadataCollector,
            $indexManager,
            $documentClass
        );
    }
}
