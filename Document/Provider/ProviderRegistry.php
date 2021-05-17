<?php

namespace Sineflow\ElasticsearchBundle\Document\Provider;

use Sineflow\ElasticsearchBundle\Manager\IndexManagerRegistry;
use Sineflow\ElasticsearchBundle\Mapping\DocumentMetadataCollector;
use Symfony\Component\DependencyInjection\ContainerAwareInterface;
use Symfony\Component\DependencyInjection\ContainerAwareTrait;

/**
 * References persistence providers for each index.
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
     * @var iterable<ProviderInterface>
     */
    private $availableProviders;

    /**
     * ProviderRegistry constructor.
     *
     * @param iterable                  $availableProviders
     * @param DocumentMetadataCollector $documentMetadataCollector
     * @param IndexManagerRegistry      $indexManagerRegistry
     * @param string                    $selfProviderClass
     */
    public function __construct(
        iterable $availableProviders,
        DocumentMetadataCollector $documentMetadataCollector,
        IndexManagerRegistry $indexManagerRegistry,
        string $selfProviderClass
    ) {
        $this->availableProviders = $availableProviders;
        $this->documentMetadataCollector = $documentMetadataCollector;
        $this->indexManagerRegistry = $indexManagerRegistry;
        $this->selfProviderClass = $selfProviderClass;
    }

    public function getProviderForEntity(string $documentClass): ?ProviderInterface
    {
        $documentMetadata = $this->documentMetadataCollector->getDocumentMetadata($documentClass);
        $providerClass = $documentMetadata->getProviderClass();

        // If a provider was specified in the entity annotation
        if ($providerClass) {
            foreach ($this->availableProviders as $provider) {
                if (get_class($provider) === $providerClass) {
                    return $provider;
                }
            }
            throw new \InvalidArgumentException(sprintf('Provider %s was not found. Make sure you have tagged it as sfes.provider or enable autowiring', $providerClass));
        }

        return null;
    }

    /**
     * Returns the self-provider if available (the provider that allows the index to rebuild from itself)
     *
     * @param string $documentClass
     *
     * @return ProviderInterface|null
     */
    public function getSelfProviderForEntity(string $documentClass): ?ProviderInterface
    {
        if (!class_exists($this->selfProviderClass)) {
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
