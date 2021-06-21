<?php

namespace Sineflow\ElasticsearchBundle\DependencyInjection\Compiler;

use Sineflow\ElasticsearchBundle\Mapping\DocumentMetadataCollector;
use Symfony\Component\Cache\Adapter\NullAdapter;
use Symfony\Component\DependencyInjection\ChildDefinition;
use Symfony\Component\DependencyInjection\Compiler\CompilerPassInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Definition;
use Symfony\Component\DependencyInjection\Reference;

class SetCachePass implements CompilerPassInterface
{
    /**
     * {@inheritdoc}
     */
    public function process(ContainerBuilder $container)
    {
        $customCachePool = $container->resolveEnvPlaceholders($container->getParameter('sfes.cache_pool'), true);

        if ($customCachePool) {
            // Use the custom metadata cache pool, if one is defined
            $cachePoolDefinition = new Reference($customCachePool);
        } else {
            if (!$container->getParameter('kernel.debug')) {
                // Use a service extending cache.system when kernel.debug is false (typically in prod and stage)
                $cachePoolDefinition = new ChildDefinition('cache.system');
                $cachePoolDefinition->addTag('cache.pool');
            } else {
                // By default, don't use cache if kernel.debug is set (typically in dev and test)
                $cachePoolDefinition = new Definition(NullAdapter::class);
            }
        }
        $container->getDefinition(DocumentMetadataCollector::class)->setArgument('$cache', $cachePoolDefinition);
    }
}
