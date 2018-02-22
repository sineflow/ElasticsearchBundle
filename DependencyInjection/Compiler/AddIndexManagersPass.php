<?php

namespace Sineflow\ElasticsearchBundle\DependencyInjection\Compiler;

use Symfony\Component\Config\Definition\Exception\InvalidConfigurationException;
use Symfony\Component\DependencyInjection\Compiler\CompilerPassInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Definition;
use Symfony\Component\DependencyInjection\Reference;

/**
 * Compiles elastic search data.
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
                throw new InvalidConfigurationException(
                    'There is no ES connection with name '.$indexSettings['connection']
                );
            }

            $indexManagerClass = $container->getParameter('sfes.index_manager.class');
            $indexManagerDefinition = new Definition(
                $indexManagerClass,
                [
                    $indexManagerClass,
                    $indexManagerName,
                    $container->getDefinition($connectionService),
                    $indexSettings,
                ]
            );

            $indexManagerDefinition->setFactory(
                [
                    new Reference('sfes.index_manager_factory'),
                    'createManager',
                ]
            );

            $container->setDefinition(
                sprintf('sfes.index.%s', $indexManagerName),
                $indexManagerDefinition
            )->setPublic(true);
        }
    }
}
