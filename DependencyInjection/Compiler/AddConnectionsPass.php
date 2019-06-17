<?php

namespace Sineflow\ElasticsearchBundle\DependencyInjection\Compiler;

use Symfony\Component\DependencyInjection\Compiler\CompilerPassInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Definition;
use Symfony\Component\DependencyInjection\Reference;

/**
 * Registers connection service definitions
 */
class AddConnectionsPass implements CompilerPassInterface
{
    /**
     * {@inheritdoc}
     */
    public function process(ContainerBuilder $container)
    {
        $connections = $container->getParameter('sfes.connections');

        // Go through each defined connection and register a manager service for each
        foreach ($connections as $connectionName => $connectionSettings) {
            $connectionName = strtolower($connectionName);

            $connectionDefinition = new Definition(
                'Sineflow\ElasticsearchBundle\Manager\ConnectionManager',
                [
                    $connectionName,
                    $connectionSettings,
                ]
            );
            $connectionDefinition->setFactory(
                [
                    new Reference('sfes.connection_factory'),
                    'createConnectionManager',
                ]
            );

            $container->setDefinition(
                sprintf('sfes.connection.%s', $connectionName),
                $connectionDefinition
            );

            if ('default' === $connectionName) {
                $container->setAlias('sfes.connection', 'sfes.connection.default');
            }
        }
    }
}
