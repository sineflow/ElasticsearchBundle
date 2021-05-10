<?php

namespace Sineflow\ElasticsearchBundle\DependencyInjection\Compiler;

use Sineflow\ElasticsearchBundle\Manager\IndexManagerInterface;
use Symfony\Component\Config\Definition\Exception\InvalidConfigurationException;
use Symfony\Component\DependencyInjection\ChildDefinition;
use Symfony\Component\DependencyInjection\Compiler\CompilerPassInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;

/**
 * Registers index manager service definitions
 */
class AddIndexManagersPass implements CompilerPassInterface
{
    /**
     * {@inheritdoc}
     */
    public function process(ContainerBuilder $container)
    {
        $indices = $container->getParameter('sfes.indices');

        // Go through each defined index and register a manager service for each
        foreach ($indices as $indexManagerName => $indexSettings) {
            $indexManagerName = strtolower($indexManagerName);

            // Make sure the connection service definition exists
            $connectionService = sprintf('sfes.connection.%s', $indexSettings['connection']);
            if (!$container->hasDefinition($connectionService)) {
                throw new InvalidConfigurationException(sprintf('There is no ES connection with name %s', $indexSettings['connection']));
            }

            $indexManagerDefinition = new ChildDefinition(IndexManagerInterface::class);
            $indexManagerDefinition->replaceArgument(0, $indexManagerName);
            $indexManagerDefinition->replaceArgument(1, $indexSettings);
            $indexManagerDefinition->replaceArgument(2, $container->findDefinition($connectionService));

            $indexManagerId = sprintf('sfes.index.%s', $indexManagerName);
            $container->setDefinition(
                $indexManagerId,
                $indexManagerDefinition
            )->setPublic(true);

            // Allow autowiring of index managers based on the argument name
            $container->registerAliasForArgument($indexManagerId, IndexManagerInterface::class, $indexManagerName . 'IndexManager');
        }
    }
}
