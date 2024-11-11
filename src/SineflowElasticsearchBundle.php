<?php

namespace Sineflow\ElasticsearchBundle;

use Sineflow\ElasticsearchBundle\DependencyInjection\Compiler\AddConnectionsPass;
use Sineflow\ElasticsearchBundle\DependencyInjection\Compiler\AddIndexManagersPass;
use Sineflow\ElasticsearchBundle\DependencyInjection\Compiler\SetCachePass;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\HttpKernel\Bundle\Bundle;

/**
 * Sineflow Elasticsearch bundle system file required by kernel.
 */
class SineflowElasticsearchBundle extends Bundle
{
    /**
     * {@inheritdoc}
     */
    public function build(ContainerBuilder $container): void
    {
        parent::build($container);

        $container->addCompilerPass(new AddConnectionsPass());
        $container->addCompilerPass(new AddIndexManagersPass());
        $container->addCompilerPass(new SetCachePass());
    }

    public function getPath(): string
    {
        return \dirname(__DIR__);
    }
}
