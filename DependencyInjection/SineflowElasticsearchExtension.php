<?php

namespace Sineflow\ElasticsearchBundle\DependencyInjection;

use Sineflow\ElasticsearchBundle\Document\Provider\ProviderInterface;
use Sineflow\ElasticsearchBundle\Document\Repository\ServiceRepositoryInterface;
use Sineflow\ElasticsearchBundle\Mapping\DocumentMetadataCollector;
use Symfony\Component\Config\FileLocator;
use Symfony\Component\DependencyInjection\ChildDefinition;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Loader;
use Symfony\Component\DependencyInjection\Reference;
use Symfony\Component\HttpKernel\DependencyInjection\Extension;

/**
 * This is the class that loads and manages bundle configuration.
 */
class SineflowElasticsearchExtension extends Extension
{
    /**
     * @param array            $config
     * @param ContainerBuilder $container
     *
     * @return Configuration
     */
    public function getConfiguration(array $config, ContainerBuilder $container)
    {
        return new Configuration();
    }

    /**
     * {@inheritdoc}
     */
    public function load(array $configs, ContainerBuilder $container)
    {
        $configuration = $this->getConfiguration($configs, $container);
        $config = $this->processConfiguration($configuration, $configs);

        $loader = new Loader\YamlFileLoader($container, new FileLocator(__DIR__.'/../Resources/config'));
        $loader->load('services.yml');

        $container->setParameter('sfes.entity_locations', $config['entity_locations']);
        $container->setParameter('sfes.connections', $config['connections']);
        $container->setParameter('sfes.indices', $config['indices']);
        $container->setParameter('sfes.languages', $config['languages']);

        $container
            ->registerForAutoconfiguration(ServiceRepositoryInterface::class)
            ->addTag('sfes.repository');

        $container
            ->registerForAutoconfiguration(ProviderInterface::class)
            ->addTag('sfes.provider');

        // Set cache pool
        if (isset($config['metadata_cache_pool'])) {
            // Use the configured metadata cache pool, if one is defined
            $cachePoolDefinition = new Reference($config['metadata_cache_pool']);
        } else {
            // Use a service extending cache.system
            $cachePoolDefinition = new ChildDefinition('cache.system');
            $cachePoolDefinition->addTag('cache.pool');
            $container->setDefinition('cache.sfes', $cachePoolDefinition);
        }
        $container->getDefinition(DocumentMetadataCollector::class)->setArgument('$cache', $cachePoolDefinition);
    }
}
