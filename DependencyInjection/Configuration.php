<?php

namespace Sineflow\ElasticsearchBundle\DependencyInjection;

use Symfony\Component\Config\Definition\Builder\NodeDefinition;
use Symfony\Component\Config\Definition\Builder\TreeBuilder;
use Symfony\Component\Config\Definition\ConfigurationInterface;
use Symfony\Component\Config\Definition\Exception\InvalidConfigurationException;

/**
 * This is the class that validates and merges configuration from App/config files.
 */
class Configuration implements ConfigurationInterface
{
    /**
     * {@inheritdoc}
     */
    public function getConfigTreeBuilder()
    {
        $treeBuilder = new TreeBuilder();
        $rootNode = $treeBuilder->root('sineflow_elasticsearch');

        $rootNode
            ->children()
                ->scalarNode('document_dir')
                    ->info("Sets directory name from which documents will be loaded from bundles.'Document' by default")
                    ->defaultValue('Document')
                ->end()
            ->append($this->getConnectionsNode())
            ->append($this->getIndicesNode())

            ->end();

        return $treeBuilder;
    }

    /**
     * Connections configuration node.
     *
     * @return NodeDefinition
     *
     * @throws InvalidConfigurationException
     */
    private function getConnectionsNode()
    {
        $builder = new TreeBuilder();
        $node = $builder->root('connections');

        $node
            ->isRequired()
            ->requiresAtLeastOneElement()
            ->useAttributeAsKey('id')
            ->info('Defines connections to Elasticsearch servers and their parameters')
            ->prototype('array')
                ->children()
                    ->arrayNode('hosts')
                        ->info('Defines hosts to connect to.')
                        ->isRequired()
                        ->requiresAtLeastOneElement()
                        ->performNoDeepMerging()
                        ->prototype('scalar')
                        ->end()
                    ->end()
                    ->scalarNode('profiling')
                        ->defaultTrue()
                        ->info('Enable/disable profiling.')
                    ->end()
                    ->scalarNode('logging')
                        ->defaultTrue()
                        ->info('Enable/disable logging.')
                    ->end()
                    ->scalarNode('bulk_batch_size')
                        ->defaultValue(1000)
                        ->info('The number of requests to send at once, when doing bulk operations')
                    ->end()

                ->end()
            ->end();

        return $node;
    }

    /**
     * Managers configuration node.
     *
     * @return NodeDefinition
     */
    private function getIndicesNode()
    {
        $builder = new TreeBuilder();
        $node = $builder->root('indices');

        $node
            ->isRequired()
            ->requiresAtLeastOneElement()
            ->useAttributeAsKey('id')
            ->info('Defines Elasticsearch indices')
            ->beforeNormalization()
                ->always(function ($v) {
                    $templates = [];
                    if (!is_array($v)) {
                        return [];
                    }
                    foreach ($v as $indexManager => $values) {
                        if ($indexManager[0] === '_') {
                            $templates[$indexManager] = $values;
                            unset($v[$indexManager]);
                        }
                        if (isset($values['extends'])) {
                            if (!isset($templates[$values['extends']])) {
                                throw new \InvalidArgumentException(sprintf('Index manager "%s" extends "%s", but it is not defined', $indexManager, $values['extends']));
                            }
                            $v[$indexManager] = array_merge($templates[$values['extends']], $v[$indexManager]);
                        }
                        unset($v[$indexManager]['extends']);
                    }

                    return $v;
                })
            ->end()
            ->prototype('array')
                ->children()
                    ->scalarNode('extends')
                        ->info('Inherit the definition of another index manager')
                    ->end()
                    ->scalarNode('connection')
                        ->isRequired()
                        ->cannotBeEmpty()
                        ->defaultValue('default')
                        ->info('Sets connection for index')
                    ->end()
                    ->scalarNode('name')
                        ->info('The name of the index in Elasticsearch')
                        ->isRequired()
                        ->cannotBeEmpty()
                    ->end()
                    ->booleanNode('use_aliases')
                        ->info('If enabled, instead of a physical index <name>, A physical index <name>_YmdHisu will be created with a <name> and <name>_write aliases pointing to it')
                        ->defaultTrue()
                    ->end()
                    ->arrayNode('settings')
                        ->defaultValue([])
                        ->info('Sets index settings')
                        ->prototype('variable')->end()
                    ->end()
                    ->scalarNode('class')
                        ->info('The entity class representing the documents in the index. Can be specified either in a short notation (e.g AppBundle:Product) or a class FQN')
                        ->isRequired()
                        ->cannotBeEmpty()
                    ->end()
                ->end()
            ->end();

        return $node;
    }
}
