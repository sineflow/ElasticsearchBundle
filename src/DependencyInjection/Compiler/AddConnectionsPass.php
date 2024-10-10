<?php

namespace Sineflow\ElasticsearchBundle\DependencyInjection\Compiler;

use Sineflow\ElasticsearchBundle\Manager\ConnectionManager;
use Symfony\Component\DependencyInjection\ChildDefinition;
use Symfony\Component\DependencyInjection\Compiler\CompilerPassInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Reference;

/**
 * Registers connection service definitions
 */
class AddConnectionsPass implements CompilerPassInterface
{
    /**
     * {@inheritdoc}
     */
    public function process(ContainerBuilder $container): void
    {
        $connections = $container->getParameter('sfes.connections');

        // Go through each defined connection and register a manager service for each
        foreach ($connections as $connectionName => $connectionSettings) {
            $connectionName = \strtolower($connectionName);

            $connectionDefinition = new ChildDefinition('sfes.connection_manager_prototype');

            $connectionDefinition->replaceArgument(0, $connectionName);
            $connectionDefinition->replaceArgument(1, $connectionSettings);

            $connectionDefinition->addMethodCall('setEventDispatcher', [new Reference('event_dispatcher')]);
            if ($connectionSettings['logging']) {
                $connectionDefinition->addMethodCall('setLogger', [new Reference('logger')]);
                $connectionDefinition->addTag('monolog.logger', ['channel' => 'sfes']);
            }
            // If profiling is enabled for the connection and the profiler is enabled for the application at all
            if ($connectionSettings['profiling'] && $container->has('profiler')) {
                $connectionDefinition->addMethodCall('setTracer', [new Reference('sfes.logger.profiler')]);
            }

            $connectionManagerId = \sprintf('sfes.connection.%s', $connectionName);
            $container->setDefinition(
                $connectionManagerId,
                $connectionDefinition
            );

            if ('sfes.connection.default' === $connectionManagerId) {
                $container->setAlias(ConnectionManager::class, $connectionManagerId);
            }

            // Allow auto-wiring of connection managers based on the argument name
            $container->registerAliasForArgument($connectionManagerId, ConnectionManager::class, $connectionName.'ConnectionManager');
        }
    }
}
