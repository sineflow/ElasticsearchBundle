<?php

namespace Sineflow\ElasticsearchBundle\Tests\Unit\DependencyInjection\Compiler;

use PHPUnit\Framework\TestCase;
use Sineflow\ElasticsearchBundle\DependencyInjection\Compiler\AddIndexManagersPass;
use Sineflow\ElasticsearchBundle\DependencyInjection\Compiler\MappingPass;
use Symfony\Component\DependencyInjection\Definition;

/**
 * Unit tests for AddConnectionsPass.
 */
class AddIndexManagersPassTest extends TestCase
{
    /**
     * Before a test method is run, a template method called setUp() is invoked.
     */
    public function testProcessWithSeveralManagers()
    {
        $connections = [
            'test1' => [
                'hosts' => ['user:pass@eshost:1111'],
                'profiling' => false,
                'logging' => false,
                'bulk_batch_size' => 123,
            ],
        ];

        $managers = [
            'test' => [
                'name' => 'testname',
                'connection' => 'test1',
                'use_aliases' => false,
                'settings' => [
                    'refresh_interval' => 2,
                    'number_of_replicas' => 3,
                ],
                'class' => 'testBundle:Foo',
            ],
        ];

        $containerMock = $this->getMockBuilder('\Symfony\Component\DependencyInjection\ContainerBuilder')
            ->disableOriginalConstructor()
            ->getMock();

        $containerMock->method('hasDefinition')->with($this->anything())
            ->will(
                $this->returnCallback(
                    function ($parameter) use ($connections, $managers) {
                        switch ($parameter) {
                            case 'sfes.connection.test1':
                                return true;
                            default:
                                return null;
                        }
                    }
                )
            );

        $containerMock->expects($this->exactly(1))->method('getParameter')->with($this->anything())
            ->will(
                $this->returnCallback(
                    function ($parameter) use ($connections, $managers) {
                        switch ($parameter) {
                            case 'sfes.indices':
                                return $managers;
                            case 'sfes.connections':
                                return $connections;
                            default:
                                return null;
                        }
                    }
                )
            );

        $containerMock
            ->expects($this->exactly(1))
            ->method('setDefinition')
            ->withConsecutive(
                [$this->equalTo('sfes.index.test')]
            )
            ->willReturn(new Definition());

        $compilerPass = new AddIndexManagersPass();
        $compilerPass->process($containerMock);
    }
}
