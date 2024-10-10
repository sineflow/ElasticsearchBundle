<?php

namespace Sineflow\ElasticsearchBundle\DependencyInjection;

use Sineflow\ElasticsearchBundle\Document\Provider\ProviderInterface;
use Sineflow\ElasticsearchBundle\Document\Repository\ServiceRepositoryInterface;
use Symfony\Component\Config\FileLocator;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Loader\YamlFileLoader;
use Symfony\Component\HttpKernel\DependencyInjection\Extension;

/**
 * This is the class that loads and manages bundle configuration.
 */
class SineflowElasticsearchExtension extends Extension
{
    public function getConfiguration(array $config, ContainerBuilder $container): Configuration
    {
        return new Configuration();
    }

    /**
     * {@inheritdoc}
     */
    public function load(array $configs, ContainerBuilder $container): void
    {
        $configuration = $this->getConfiguration($configs, $container);
        $config = $this->processConfiguration($configuration, $configs);

        $loader = new YamlFileLoader($container, new FileLocator(__DIR__.'/../../config'));
        $loader->load('services.yml');

        $container->setParameter('sfes.entity_locations', $config['entity_locations']);
        $container->setParameter('sfes.connections', $config['connections']);
        $container->setParameter('sfes.indices', $config['indices']);
        $container->setParameter('sfes.languages', $config['languages']);
        $container->setParameter('sfes.cache_pool', $config['metadata_cache_pool'] ?? null);

        $container
            ->registerForAutoconfiguration(ServiceRepositoryInterface::class)
            ->addTag('sfes.repository');

        $container
            ->registerForAutoconfiguration(ProviderInterface::class)
            ->addTag('sfes.provider');
    }
}
