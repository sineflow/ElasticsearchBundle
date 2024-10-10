<?php

namespace Sineflow\ElasticsearchBundle\DependencyInjection\Compiler;

use Sineflow\ElasticsearchBundle\Manager\IndexManager;
use Symfony\Component\Config\Definition\Exception\InvalidConfigurationException;
use Symfony\Component\DependencyInjection\ChildDefinition;
use Symfony\Component\DependencyInjection\Compiler\CompilerPassInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Reference;

/**
 * Registers index manager service definitions
 */
class AddIndexManagersPass implements CompilerPassInterface
{
    /**
     * {@inheritdoc}
     */
    public function process(ContainerBuilder $container): void
    {
        $indices = $container->getParameter('sfes.indices');

        // Go through each defined index and register a manager service for each
        foreach ($indices as $indexManagerName => $indexSettings) {
            $indexManagerName = \strtolower($indexManagerName);

            // Make sure the connection service definition exists
            $connectionService = \sprintf('sfes.connection.%s', $indexSettings['connection']);
            if (!$container->hasDefinition($connectionService)) {
                throw new InvalidConfigurationException(\sprintf('There is no ES connection with name %s', $indexSettings['connection']));
            }

            $indexManagerDefinition = new ChildDefinition('sfes.index_manager_prototype');
            $indexManagerDefinition->replaceArgument(0, $indexManagerName);
            $indexManagerDefinition->replaceArgument(1, $indexSettings);
            $indexManagerDefinition->replaceArgument(2, new Reference($connectionService));
            $indexManagerDefinition->addMethodCall('setEventDispatcher', [new Reference('event_dispatcher')]);
            $indexManagerDefinition->addTag('sfes.index_manager');

            $indexManagerId = \sprintf('sfes.index.%s', $indexManagerName);
            $container->setDefinition(
                $indexManagerId,
                $indexManagerDefinition
            );

            // Allow auto-wiring of index managers based on the argument name
            // If sfes.index_manager_prototype has been overridden, register the overriding class as an alias as well
            $indexManagerClass = $container->getDefinition('sfes.index_manager_prototype')->getClass();
            $container->registerAliasForArgument($indexManagerId, $indexManagerClass, $indexManagerName.'IndexManager');
            if (IndexManager::class !== $indexManagerClass) {
                $container->registerAliasForArgument($indexManagerId, IndexManager::class, $indexManagerName.'IndexManager');
            }
        }
    }
}
