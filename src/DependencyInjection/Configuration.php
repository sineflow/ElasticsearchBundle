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
    public function getConfigTreeBuilder(): TreeBuilder
    {
        $treeBuilder = new TreeBuilder('sineflow_elasticsearch');
        $rootNode = $treeBuilder->getRootNode();

        $rootNode
            ->children()
                ->arrayNode('languages')
                    ->prototype('scalar')
                    ->end()
                ->end()
                ->scalarNode('metadata_cache_pool')->end()
                ->append($this->getEntityLocationsNode())
                ->append($this->getConnectionsNode())
                ->append($this->getIndicesNode())
            ->end();

        return $treeBuilder;
    }

    /**
     * Connections configuration node.
     *
     * @throws InvalidConfigurationException
     */
    private function getEntityLocationsNode(): NodeDefinition
    {
        $builder = new TreeBuilder('entity_locations');
        $node = $builder->getRootNode();

        $node
            ->isRequired()
            ->requiresAtLeastOneElement()
            ->useAttributeAsKey('id')
            ->info('Defines locations of Elasticsearch entities')
            ->prototype('array')
                ->children()
                    ->scalarNode('directory')
                        ->info('The path to where the Elasticsearch entities are')
                        ->isRequired()
                        ->cannotBeEmpty()
                    ->end()
                    ->scalarNode('namespace')
                        ->info('The namespace of the entities in this location')
                        ->isRequired()
                        ->cannotBeEmpty()
                    ->end()
                ->end()
            ->end();

        return $node;
    }

    /**
     * Connections configuration node.
     *
     * @throws InvalidConfigurationException
     */
    private function getConnectionsNode(): NodeDefinition
    {
        $builder = new TreeBuilder('connections');
        $node = $builder->getRootNode();

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
                    ->scalarNode('ssl_verification')
                        ->defaultNull()
                        ->info('Set to true to enable SSL certificate verification and use the default CA bundle provided by operating system or provide the path to a CA bundle to enable verification using a custom certificate')
                    ->end()
                    ->scalarNode('profiling')
                        ->defaultTrue()
                        ->info('Enable/disable profiling.')
                    ->end()
                    ->scalarNode('profiling_backtrace')
                        ->defaultFalse()
                        ->info('Enable/disable backtrace info when profiling is enabled (has performance impact).')
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
     */
    private function getIndicesNode(): NodeDefinition
    {
        $builder = new TreeBuilder('indices');
        $node = $builder->getRootNode();

        $node
            ->isRequired()
            ->requiresAtLeastOneElement()
            ->useAttributeAsKey('id')
            ->info('Defines Elasticsearch indices')
            ->beforeNormalization()
                ->always(static function ($v) {
                    $templates = [];
                    if (!\is_array($v)) {
                        return [];
                    }
                    foreach ($v as $indexManager => $values) {
                        if ('_' === $indexManager[0]) {
                            $templates[$indexManager] = $values;
                            unset($v[$indexManager]);
                        }
                        if (isset($values['extends'])) {
                            if (!isset($templates[$values['extends']])) {
                                throw new \InvalidArgumentException(\sprintf('Index manager "%s" extends "%s", but it is not defined', $indexManager, $values['extends']));
                            }
                            $v[$indexManager] = \array_replace_recursive($templates[$values['extends']], $v[$indexManager]);
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
                        ->info('The entity class representing the documents in the index. Can be specified either in a short notation (e.g App:Product) or a class FQN')
                        ->isRequired()
                        ->cannotBeEmpty()
                    ->end()
                ->end()
            ->end();

        return $node;
    }
}
