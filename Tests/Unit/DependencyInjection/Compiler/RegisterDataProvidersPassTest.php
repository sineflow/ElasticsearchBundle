<?php

namespace Sineflow\ElasticsearchBundle\Tests\Unit\DependencyInjection\Compiler;

use PHPUnit\Framework\TestCase;
use Sineflow\ElasticsearchBundle\DependencyInjection\Compiler\MappingPass;
use Sineflow\ElasticsearchBundle\DependencyInjection\Compiler\RegisterDataProvidersPass;
use Sineflow\ElasticsearchBundle\Document\Provider\ProviderRegistry;
use Symfony\Component\DependencyInjection\Definition;

/**
 * Unit tests for AddConnectionsPass.
 */
class RegisterDataProvidersPassTest extends TestCase
{
    /**
     * Before a test method is run, a template method called setUp() is invoked.
     */
    public function testProcessWithElasticsearchProvider()
    {
        $containerMock = $this->getMockBuilder('\Symfony\Component\DependencyInjection\ContainerBuilder')
            ->disableOriginalConstructor()
            ->getMock();

        $containerMock->method('hasDefinition')->with(ProviderRegistry::class)->willReturn(true);

        $containerMock->expects($this->exactly(1))->method('findTaggedServiceIds')->willReturn(
            [
                'app.es.data_provider.mytype' =>
                    [
                        0 =>
                            [
                                'type' => 'App:MyType',
                            ],
                    ],
                'app.es.data_provider.mytype2' =>
                    [
                        0 =>
                            [
                                'type' => 'App:MyType2',
                            ],
                    ],
            ]
        );

        $containerMock->expects($this->exactly(3))->method('findDefinition')->with($this->anything())
            ->will(
                $this->returnCallback(
                    function ($parameter) {
                        switch ($parameter) {
                            case ProviderRegistry::class:
                                return new Definition(ProviderRegistry::class);
                            case 'app.es.data_provider.mytype':
                            case 'app.es.data_provider.mytype2':
                                return new Definition('\Sineflow\ElasticsearchBundle\Document\Provider\ElasticsearchProvider');
                            default:
                                return null;
                        }
                    }
                )
            );

        $compilerPass = new RegisterDataProvidersPass();
        $compilerPass->process($containerMock);
    }

    /**
     * Test registering a provider that does not have a type tag set
     */
    public function testProcessWithProviderWithoutTypeTag()
    {
        $containerMock = $this->getMockBuilder('\Symfony\Component\DependencyInjection\ContainerBuilder')
            ->disableOriginalConstructor()
            ->getMock();

        $containerMock->method('hasDefinition')->with(ProviderRegistry::class)->willReturn(true);

        $containerMock->expects($this->exactly(1))->method('findTaggedServiceIds')->willReturn(
            [
                'app.es.data_provider.notype' =>
                    [
                        0 => [],
                    ],
            ]
        );

        $containerMock->expects($this->exactly(2))->method('findDefinition')->with($this->anything())
            ->will(
                $this->returnCallback(
                    function ($parameter) {
                        switch ($parameter) {
                            case ProviderRegistry::class:
                                return new Definition(ProviderRegistry::class);
                            case 'app.es.data_provider.notype':
                                return new Definition('\Sineflow\ElasticsearchBundle\Document\Provider\ElasticsearchProvider');
                            default:
                                return null;
                        }
                    }
                )
            );

        $compilerPass = new RegisterDataProvidersPass();
        $this->expectException(\InvalidArgumentException::class);
        $compilerPass->process($containerMock);
    }

    public function testProcessWithSameProviderForSeveralTypes()
    {
        $containerMock = $this->getMockBuilder('\Symfony\Component\DependencyInjection\ContainerBuilder')
            ->disableOriginalConstructor()
            ->getMock();

        $containerMock->method('hasDefinition')->with(ProviderRegistry::class)->willReturn(true);

        $containerMock->expects($this->exactly(1))->method('findTaggedServiceIds')->willReturn(
            [
                'app.es.data_provider.dummy' =>
                    [
                        0 =>
                            [
                                'type' => 'App:MyType1',
                            ],
                            1 =>
                            [
                                'type' => 'App:MyType2',
                            ],
                    ],
            ]
        );

        $providerDefinitionMock = $this->getMockBuilder('\Symfony\Component\DependencyInjection\Definition')
            ->disableOriginalConstructor()
            ->getMock();

        $containerMock->expects($this->exactly(2))->method('findDefinition')->with($this->anything())
            ->will(
                $this->returnCallback(
                    function ($parameter) use ($providerDefinitionMock) {
                        switch ($parameter) {
                            case ProviderRegistry::class:
                                return $providerDefinitionMock;
                            case 'app.es.data_provider.dummy':
                                return new Definition('\Sineflow\ElasticsearchBundle\Tests\App\fixture\Acme\FooBundle\Document\Provider\OrderProvider');
                            default:
                                return null;
                        }
                    }
                )
            );

        $providerDefinitionMock
            ->expects($this->exactly(2))
            ->method('addMethodCall')
            ->withConsecutive(
                array($this->equalTo('addProvider'), $this->equalTo(['App:MyType1', 'app.es.data_provider.dummy'])),
                array($this->equalTo('addProvider'), $this->equalTo(['App:MyType2', 'app.es.data_provider.dummy']))
            );

        $compilerPass = new RegisterDataProvidersPass();
        $compilerPass->process($containerMock);
    }
}
