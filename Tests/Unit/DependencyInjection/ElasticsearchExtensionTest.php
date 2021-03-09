<?php

namespace Sineflow\ElasticsearchBundle\Tests\Unit\DependencyInjection;

use PHPUnit\Framework\TestCase;
use Sineflow\ElasticsearchBundle\DependencyInjection\SineflowElasticsearchExtension;
use Sineflow\ElasticsearchBundle\Document\Provider\ProviderRegistry;
use Sineflow\ElasticsearchBundle\Finder\Finder;
use Sineflow\ElasticsearchBundle\Manager\ConnectionManagerFactory;
use Sineflow\ElasticsearchBundle\Manager\IndexManagerFactory;
use Sineflow\ElasticsearchBundle\Manager\IndexManagerRegistry;
use Sineflow\ElasticsearchBundle\Mapping\DocumentLocator;
use Sineflow\ElasticsearchBundle\Mapping\DocumentMetadataCollector;
use Sineflow\ElasticsearchBundle\Mapping\DocumentParser;
use Sineflow\ElasticsearchBundle\Profiler\ElasticsearchProfiler;
use Sineflow\ElasticsearchBundle\Profiler\Handler\CollectionHandler;
use Sineflow\ElasticsearchBundle\Result\DocumentConverter;
use Sineflow\ElasticsearchBundle\Subscriber\EntityTrackerSubscriber;
use Sineflow\ElasticsearchBundle\Subscriber\KnpPaginateQuerySubscriber;
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
                'entity_locations' => [
                    'AcmeBarBundle' => [
                        'directory' => 'Tests/App/fixture/Acme/BarBundle/Document',
                        'namespace' => 'Sineflow\ElasticsearchBundle\Tests\App\fixture\Acme\BarBundle\Document',
                    ],
                ],

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
                        'class' => 'testBundle:Foo',
                    ],
                ],
            ],
        ];

        $expectedEntityLocations = [
            'AcmeBarBundle' => [
                'directory' => 'Tests/App/fixture/Acme/BarBundle/Document',
                'namespace' => 'Sineflow\ElasticsearchBundle\Tests\App\fixture\Acme\BarBundle\Document',
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
                'class' => 'testBundle:Foo',
            ],
        ];

        $out[] = [
            $parameters,
            $expectedEntityLocations,
            $expectedConnections,
            $expectedManagers,
        ];

        return $out;
    }

    /**
     * Check if load adds parameters to container as expected.
     *
     * @param array $parameters
     * @param array $expectedEntityLocations
     * @param array $expectedConnections
     * @param array $expectedManagers
     *
     * @dataProvider getData
     */
    public function testLoad($parameters, $expectedEntityLocations, $expectedConnections, $expectedManagers)
    {
        $container = new ContainerBuilder();
        class_exists('testClass') ? : eval('class testClass {}');
        $container->setParameter('kernel.cache_dir', '');
        $container->setParameter('kernel.logs_dir', '');
        $container->setParameter('kernel.debug', true);
        $extension = new SineflowElasticsearchExtension();
        $extension->load(
            $parameters,
            $container
        );

        $this->assertEquals(
            $expectedEntityLocations,
            $container->getParameter('sfes.entity_locations'),
            'Incorrect entity_locations parameter.'
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

        $expectedServiceDefinitions = [
            DocumentConverter::class,
            ProviderRegistry::class,
            IndexManagerFactory::class,
            IndexManagerRegistry::class,
            Finder::class,
            DocumentLocator::class,
            DocumentParser::class,
            DocumentMetadataCollector::class,
            CollectionHandler::class,
            ConnectionManagerFactory::class,
            ElasticsearchProfiler::class,
            KnpPaginateQuerySubscriber::class,
            EntityTrackerSubscriber::class,
        ];
        foreach ($expectedServiceDefinitions as $expectedServiceDefinition) {
            $this->assertTrue(
                $container->hasDefinition($expectedServiceDefinition),
                sprintf('Container should have [%s] definition set.', $expectedServiceDefinition)
            );
        }
    }
}
