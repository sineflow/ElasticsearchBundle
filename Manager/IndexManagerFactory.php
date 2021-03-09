<?php

namespace Sineflow\ElasticsearchBundle\Manager;

use Sineflow\ElasticsearchBundle\Document\Provider\ProviderRegistry;
use Sineflow\ElasticsearchBundle\Finder\Finder;
use Sineflow\ElasticsearchBundle\Mapping\DocumentMetadataCollector;
use Sineflow\ElasticsearchBundle\Result\DocumentConverter;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;

/**
 * Factory for index manager services
 */
class IndexManagerFactory
{
    /**
     * @var DocumentMetadataCollector
     */
    private $metadataCollector;

    /**
     * @var ProviderRegistry
     */
    private $providerRegistry;

    /**
     * @var Finder
     */
    private $finder;

    /**
     * @var DocumentConverter
     */
    private $documentConverter;

    /**
     * @var string The separator string between property names and language codes for ML properties
     */
    private $languageSeparator;

    /**
     * @var EventDispatcherInterface
     */
    protected $eventDispatcher;

    /**
     * @param DocumentMetadataCollector $metadataCollector
     * @param ProviderRegistry          $providerRegistry
     * @param Finder                    $finder
     * @param DocumentConverter         $documentConverter
     * @param string                    $languageSeparator
     */
    public function __construct(
        DocumentMetadataCollector $metadataCollector,
        ProviderRegistry $providerRegistry,
        Finder $finder,
        DocumentConverter $documentConverter,
        $languageSeparator
    ) {
        $this->metadataCollector = $metadataCollector;
        $this->providerRegistry = $providerRegistry;
        $this->finder = $finder;
        $this->documentConverter = $documentConverter;
        $this->languageSeparator = $languageSeparator;
    }

    /**
     * @return EventDispatcherInterface
     */
    public function getEventDispatcher()
    {
        return $this->eventDispatcher;
    }

    /**
     * @param EventDispatcherInterface $eventDispatcher
     */
    public function setEventDispatcher($eventDispatcher)
    {
        $this->eventDispatcher = $eventDispatcher;
    }

    /**
     * @param string            $managerClass
     * @param string            $managerName
     * @param ConnectionManager $connection
     * @param array             $indexSettings
     *
     * @return IndexManager
     */
    public function createManager(
        $managerClass,
        $managerName,
        ConnectionManager $connection,
        array $indexSettings
    ) {
        /** @var IndexManager $manager */
        $manager = new $managerClass(
            $managerName,
            $connection,
            $this->metadataCollector,
            $this->providerRegistry,
            $this->finder,
            $this->documentConverter,
            $indexSettings,
            $this->languageSeparator
        );

        $manager->setEventDispatcher($this->eventDispatcher);

        return $manager;
    }
}
