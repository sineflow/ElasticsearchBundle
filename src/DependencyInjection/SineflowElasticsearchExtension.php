<?php

namespace Sineflow\ElasticsearchBundle\DependencyInjection;

use Doctrine\Common\Annotations\AnnotationReader;
use Sineflow\ElasticsearchBundle\Document\Provider\ProviderInterface;
use Sineflow\ElasticsearchBundle\Document\Repository\ServiceRepositoryInterface;
use Sineflow\ElasticsearchBundle\Mapping\DocumentLocator;
use Sineflow\ElasticsearchBundle\Mapping\DocumentParser;
use Symfony\Component\Config\FileLocator;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Loader\YamlFileLoader;
use Symfony\Component\DependencyInjection\Reference;
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

        // Conditionally register the annotation_reader service, only if doctrine/annotations is installed.
        // This will not be needed when support for annotations is removed.
        if (class_exists(AnnotationReader::class)) {
            $container->register('annotation_reader', AnnotationReader::class)
                      ->setPublic(true);

            $container->register(DocumentParser::class, DocumentParser::class)
                      ->setArguments([
                          new Reference('annotation_reader'),
                          new Reference(DocumentLocator::class),
                          '%sfes.mlproperty.language_separator%',
                          '%sfes.languages%',
                      ]);
        }

        $loader = new YamlFileLoader($container, new FileLocator(__DIR__.'/../../config'));
        $loader->load('services.yml');

        $container->setParameter('sfes.entity_locations', $config['entity_locations']);
        $container->setParameter('sfes.connections', $config['connections']);
        $container->setParameter('sfes.indices', $config['indices']);
        $container->setParameter('sfes.languages', $config['languages']);
        $container->setParameter('sfes.cache_pool', $config['metadata_cache_pool'] ?? null);
        $container->setParameter('sfes.use_annotations', $config['use_annotations']);

        $container
            ->registerForAutoconfiguration(ServiceRepositoryInterface::class)
            ->addTag('sfes.repository');

        $container
            ->registerForAutoconfiguration(ProviderInterface::class)
            ->addTag('sfes.provider');
    }
}
