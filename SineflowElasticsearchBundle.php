<?php

namespace Sineflow\ElasticsearchBundle;

use Sineflow\ElasticsearchBundle\DependencyInjection\Compiler\AddConnectionsPass;
use Sineflow\ElasticsearchBundle\DependencyInjection\Compiler\AddIndexManagersPass;
use Sineflow\ElasticsearchBundle\DependencyInjection\Compiler\RegisterLanguageProviderPass;
use Sineflow\ElasticsearchBundle\Document\Provider\ProviderInterface;
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
    public function build(ContainerBuilder $container)
    {
        parent::build($container);

        $container->addCompilerPass(new AddConnectionsPass());
        $container->addCompilerPass(new RegisterLanguageProviderPass());
        $container->addCompilerPass(new AddIndexManagersPass());

        $container
            ->registerForAutoconfiguration(ProviderInterface::class)
            ->addTag('sfes.provider');
    }
}
