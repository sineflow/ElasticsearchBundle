<?php

namespace Sineflow\ElasticsearchBundle\Tests\Unit\DependencyInjection\Compiler;

use PHPUnit\Framework\TestCase;
use Sineflow\ElasticsearchBundle\DependencyInjection\Compiler\AddIndexManagersPass;
use Sineflow\ElasticsearchBundle\Manager\IndexManager;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Definition;

/**
 * Unit tests for AddConnectionsPass.
 */
class AddIndexManagersPassTest extends TestCase
{
    /**
     * Before a test method is run, a template method called setUp() is invoked.
     */
    public function testProcessWithSeveralManagers(): void
    {
        $connections = [
            'test1' => [
                'hosts'           => ['user:pass@eshost:1111'],
                'profiling'       => false,
                'logging'         => false,
                'bulk_batch_size' => 123,
            ],
        ];

        $managers = [
            'test' => [
                'name'        => 'testname',
                'connection'  => 'test1',
                'use_aliases' => false,
                'settings'    => [
                    'refresh_interval'   => 2,
                    'number_of_replicas' => 3,
                ],
                'class' => 'testBundle:Foo',
            ],
        ];

        $containerMock = $this->getMockBuilder(ContainerBuilder::class)
            ->disableOriginalConstructor()
            ->getMock();

        $containerMock->method('hasDefinition')->with($this->anything())
            ->willReturnCallback(
                static fn ($parameter): ?bool => match ($parameter) {
                    'sfes.connection.test1' => true,
                    default                 => null,
                }
            );

        $containerMock->expects($this->exactly(1))->method('getParameter')->with($this->anything())
            ->willReturnCallback(
                static fn ($parameter): ?array => match ($parameter) {
                    'sfes.indices'     => $managers,
                    'sfes.connections' => $connections,
                    default            => null,
                }
            );
        $matcher = $this->exactly(1);

        $containerMock
            ->expects($matcher)
            ->method('setDefinition')
            ->willReturnCallback(
                function (...$parameters) use ($matcher) {
                    if (1 === $matcher->numberOfInvocations()) {
                        $this->assertSame('sfes.index.test', $parameters[0]);
                    }

                    return new Definition();
                }
            );

        $imPrototypeDefinitionMock = $this->getMockBuilder(Definition::class)
            ->getMock();
        $imPrototypeDefinitionMock
            ->method('getClass')
            ->willReturn(IndexManager::class);

        $containerMock
            ->expects($this->exactly(1))
            ->method('getDefinition')
            ->with('sfes.index_manager_prototype')
            ->willReturn($imPrototypeDefinitionMock);

        $compilerPass = new AddIndexManagersPass();
        $compilerPass->process($containerMock);
    }
}
