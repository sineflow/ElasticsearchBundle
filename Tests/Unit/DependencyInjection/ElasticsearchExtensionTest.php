<?php

namespace Sineflow\ElasticsearchBundle\Tests\Unit\DependencyInjection;

use PHPUnit\Framework\TestCase;
use Sineflow\ElasticsearchBundle\DependencyInjection\SineflowElasticsearchExtension;
use Symfony\Component\DependencyInjection\ContainerBuilder;

/**
 * Unit tests for ElasticsearchExtension.
 */
class ElasticsearchExtensionTest extends TestCase
{
    /**
     * @return array
     */
    public function getData()
    {
        $parameters = [
            'sineflow_elasticsearch' => [
                'document_dir' => 'ElasticSearch/Document',
                'connections' => [
                    'test1' => [
                        'hosts' => [
                            'user:pass@eshost:1111'
                        ],
                        'profiling' => false,
                        'logging' => false,
                        'bulk_batch_size' => 123,
                    ],
                ],
                'indices' => [
                    'test' => [
                        'name' => 'testname',
                        'connection' => 'test1',
                        'use_aliases' => false,
                        'settings' => [
                            'refresh_interval' => 2,
                            'number_of_replicas' => 3,
                            'analysis' => [
                                'filter' => [
                                    'test_filter' => [
                                        'type' => 'ngram'
                                    ]
                                ],
                                'tokenizer' => [
                                    'test_tokenizer' => [
                                        'type' => 'ngram'
                                    ]
                                ],
                                'analyzer' => [
                                    'test_analyzer' => [
                                        'type' => 'custom'
                                    ]
                                ]
                            ]
                        ],
                        'types' => [
                            'testBundle:Foo',
                            'testBundle:Bar',
                        ],
                    ],
                ],
            ],
        ];

        $expectedConnections = [
            'test1' => [
                'hosts' => ['user:pass@eshost:1111'],
                'profiling' => false,
                'logging' => false,
                'bulk_batch_size' => 123,
            ],
        ];

        $expectedManagers = [
            'test' => [
                'name' => 'testname',
                'connection' => 'test1',
                'use_aliases' => false,
                'settings' => [
                    'refresh_interval' => 2,
                    'number_of_replicas' => 3,
                    'analysis' => [
                        'filter' => [
                            'test_filter' => [
                                'type' => 'ngram'
                            ]
                        ],
                        'tokenizer' => [
                            'test_tokenizer' => [
                                'type' => 'ngram'
                            ]
                        ],
                        'analyzer' => [
                            'test_analyzer' => [
                                'type' => 'custom'
                            ]
                        ]
                    ]
                ],
                'types' => [
                    'testBundle:Foo',
                    'testBundle:Bar',
                ],
            ],
        ];

        $out[] = [
            $parameters,
            $expectedConnections,
            $expectedManagers,
        ];

        return $out;
    }

    /**
     * Check if load adds parameters to container as expected.
     *
     * @param array $parameters
     * @param array $expectedConnections
     * @param array $expectedManagers
     *
     * @dataProvider getData
     */
    public function testLoad($parameters, $expectedConnections, $expectedManagers)
    {
        $container = new ContainerBuilder();
        class_exists('testClass') ? : eval('class testClass {}');
        $container->setParameter('kernel.bundles', ['testBundle' => 'testClass']);
        $container->setParameter('kernel.cache_dir', '');
        $container->setParameter('kernel.logs_dir', '');
        $container->setParameter('kernel.debug', true);
        $extension = new SineflowElasticsearchExtension();
        $extension->load(
            $parameters,
            $container
        );

        $this->assertEquals(
            $expectedConnections,
            $container->getParameter('sfes.connections'),
            'Incorrect connections parameter.'
        );
        $this->assertEquals(
            $expectedManagers,
            $container->getParameter('sfes.indices'),
            'Incorrect index managers parameter'
        );
        $this->assertTrue(
            $container->hasDefinition('sfes.document_converter'),
            'Container should have sfes.document_converter definition set.'
        );
        $this->assertTrue(
            $container->hasDefinition('sfes.provider_registry'),
            'Container should have sfes.provider_registry definition set.'
        );
        $this->assertTrue(
            $container->hasDefinition('sfes.index_manager_factory'),
            'Container should have sfes.index_manager_factory definition set.'
        );
        $this->assertTrue(
            $container->hasDefinition('sfes.index_manager_registry'),
            'Container should have sfes.index_manager_registry definition set.'
        );
        $this->assertTrue(
            $container->hasDefinition('sfes.finder'),
            'Container should have sfes.finder definition set.'
        );
        $this->assertTrue(
            $container->hasDefinition('sfes.document_locator'),
            'Container should have sfes.document_locator definition set.'
        );
        $this->assertTrue(
            $container->hasDefinition('sfes.cache_engine'),
            'Container should have sfes.cache_engine definition set.'
        );
        $this->assertTrue(
            $container->hasDefinition('sfes.annotations.reader'),
            'Container should have sfes.annotations.reader definition set.'
        );
        $this->assertTrue(
            $container->hasDefinition('sfes.provider_registry'),
            'Container should have sfes.provider_registry definition set.'
        );
        $this->assertTrue(
            $container->hasDefinition('sfes.document_parser'),
            'Container should have sfes.document_parser definition set.'
        );
        $this->assertTrue(
            $container->hasDefinition('sfes.document_metadata_collector'),
            'Container should have sfes.document_metadata_collector definition set.'
        );
        $this->assertTrue(
            $container->hasDefinition('sfes.logger.collection_handler'),
            'Container should have sfes.logger.collection_handler definition set.'
        );
        $this->assertTrue(
            $container->hasDefinition('sfes.connection_factory'),
            'Container should have sfes.connection_factory definition set.'
        );
        $this->assertTrue(
            $container->hasDefinition('sfes.profiler'),
            'Container should have sfes.profiler definition set.'
        );
    }
}
