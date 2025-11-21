<?php

namespace Sineflow\ElasticsearchBundle\Tests\Unit\DependencyInjection\Compiler;

use PHPUnit\Framework\TestCase;
use Sineflow\ElasticsearchBundle\DependencyInjection\Compiler\AddConnectionsPass;
use Sineflow\ElasticsearchBundle\Manager\ConnectionManager;
use Symfony\Component\DependencyInjection\ChildDefinition;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Definition;
use Symfony\Component\DependencyInjection\Reference;

/**
 * Unit tests for AddConnectionsPass.
 */
class AddConnectionsPassTest extends TestCase
{
    public function testProcessWithBasicConnection(): void
    {
        $connections = [
            'test_connection' => [
                'hosts'               => ['localhost:9200'],
                'profiling'           => false,
                'logging'             => false,
                'bulk_batch_size'     => 100,
                'http_client_options' => [],
                'http_client_service' => null,
            ],
        ];

        $container = new ContainerBuilder();
        $container->setParameter('sfes.connections', $connections);

        // Create the prototype definition
        $prototypeDefinition = new Definition(ConnectionManager::class);
        $prototypeDefinition->setArguments([
            null, // connection name
            null, // connection settings
            '%kernel.debug%', // kernel debug
            null, // HTTP client service
        ]);
        $container->setDefinition('sfes.connection_manager_prototype', $prototypeDefinition);

        // Mock event_dispatcher and logger services
        $container->setDefinition('event_dispatcher', new Definition());
        $container->setDefinition('logger', new Definition());

        $compilerPass = new AddConnectionsPass();
        $compilerPass->process($container);

        // Assert the connection manager service was created
        $this->assertTrue($container->hasDefinition('sfes.connection.test_connection'));

        $connectionDefinition = $container->getDefinition('sfes.connection.test_connection');

        // Verify it's a child definition
        $this->assertInstanceOf(ChildDefinition::class, $connectionDefinition);
        $this->assertSame('sfes.connection_manager_prototype', $connectionDefinition->getParent());

        // Verify arguments are set correctly
        $this->assertSame('test_connection', $connectionDefinition->getArgument(0));
        $this->assertSame($connections['test_connection'], $connectionDefinition->getArgument(1));

        // Verify argument 3 (HTTP client) is not set (should remain as parent's default)
        // Note: ChildDefinition stores replaced arguments with string keys like "index_3"
        $arguments = $connectionDefinition->getArguments();
        $this->assertArrayNotHasKey('index_3', $arguments, 'Argument 3 should not be set when http_client_service is null');
    }

    public function testProcessWithHttpClientService(): void
    {
        $connections = [
            'test_with_custom_client' => [
                'hosts'               => ['localhost:9200'],
                'profiling'           => false,
                'logging'             => false,
                'bulk_batch_size'     => 100,
                'http_client_options' => [],
                'http_client_service' => 'app.custom_http_client',
            ],
        ];

        $container = new ContainerBuilder();
        $container->setParameter('sfes.connections', $connections);

        // Create the prototype definition
        $prototypeDefinition = new Definition(ConnectionManager::class);
        $prototypeDefinition->setArguments([
            null, // connection name
            null, // connection settings
            '%kernel.debug%', // kernel debug
            null, // HTTP client service
        ]);
        $container->setDefinition('sfes.connection_manager_prototype', $prototypeDefinition);

        // Mock services
        $container->setDefinition('event_dispatcher', new Definition());
        $container->setDefinition('logger', new Definition());
        $container->setDefinition('app.custom_http_client', new Definition());

        $compilerPass = new AddConnectionsPass();
        $compilerPass->process($container);

        $connectionDefinition = $container->getDefinition('sfes.connection.test_with_custom_client');

        // Verify the HTTP client service reference is injected as argument 3
        // Note: ChildDefinition stores replaced arguments with string keys like "index_3"
        $arguments = $connectionDefinition->getArguments();
        $this->assertArrayHasKey('index_3', $arguments, 'Connection definition should have argument 3 set when http_client_service is specified');

        $httpClientArgument = $connectionDefinition->getArgument(3);
        $this->assertInstanceOf(Reference::class, $httpClientArgument, 'Argument 3 should be a Reference to the HTTP client service');
        $this->assertSame('app.custom_http_client', (string) $httpClientArgument, 'HTTP client service reference should point to the correct service');
    }

    public function testProcessWithHttpClientOptions(): void
    {
        $httpClientOptions = [
            'timeout'         => 30,
            'connect_timeout' => 5,
            'max_duration'    => 86400,
        ];

        $connections = [
            'test_with_options' => [
                'hosts'               => ['localhost:9200'],
                'profiling'           => false,
                'logging'             => false,
                'bulk_batch_size'     => 100,
                'http_client_options' => $httpClientOptions,
                'http_client_service' => null,
            ],
        ];

        $container = new ContainerBuilder();
        $container->setParameter('sfes.connections', $connections);

        // Create the prototype definition
        $prototypeDefinition = new Definition(ConnectionManager::class);
        $prototypeDefinition->setArguments([
            null,
            null,
            '%kernel.debug%',
            null,
        ]);
        $container->setDefinition('sfes.connection_manager_prototype', $prototypeDefinition);

        $container->setDefinition('event_dispatcher', new Definition());
        $container->setDefinition('logger', new Definition());

        $compilerPass = new AddConnectionsPass();
        $compilerPass->process($container);

        $connectionDefinition = $container->getDefinition('sfes.connection.test_with_options');

        // Verify the connection settings (argument 1) contain the http_client_options
        $connectionSettings = $connectionDefinition->getArgument(1);
        $this->assertArrayHasKey('http_client_options', $connectionSettings);
        $this->assertSame($httpClientOptions, $connectionSettings['http_client_options'], 'HTTP client options should be passed through in connection settings');
    }

    public function testProcessWithNestedHttpClientOptions(): void
    {
        $nestedOptions = [
            'timeout' => 30,
            'extra'   => [
                'curl' => [
                    CURLOPT_SSL_VERIFYPEER => false,
                    CURLOPT_SSL_VERIFYHOST => 0,
                ],
            ],
        ];

        $connections = [
            'test_with_nested_options' => [
                'hosts'               => ['localhost:9200'],
                'profiling'           => false,
                'logging'             => false,
                'bulk_batch_size'     => 100,
                'http_client_options' => $nestedOptions,
                'http_client_service' => null,
            ],
        ];

        $container = new ContainerBuilder();
        $container->setParameter('sfes.connections', $connections);

        $prototypeDefinition = new Definition(ConnectionManager::class);
        $prototypeDefinition->setArguments([null, null, '%kernel.debug%', null]);
        $container->setDefinition('sfes.connection_manager_prototype', $prototypeDefinition);

        $container->setDefinition('event_dispatcher', new Definition());
        $container->setDefinition('logger', new Definition());

        $compilerPass = new AddConnectionsPass();
        $compilerPass->process($container);

        $connectionDefinition = $container->getDefinition('sfes.connection.test_with_nested_options');
        $connectionSettings = $connectionDefinition->getArgument(1);

        $this->assertArrayHasKey('http_client_options', $connectionSettings);
        $this->assertSame($nestedOptions, $connectionSettings['http_client_options'], 'Nested HTTP client options should be preserved exactly');
        $this->assertIsArray($connectionSettings['http_client_options']['extra']);
        $this->assertIsArray($connectionSettings['http_client_options']['extra']['curl']);
    }

    public function testProcessWithBothHttpClientServiceAndOptions(): void
    {
        $httpClientOptions = [
            'timeout'      => 60,
            'max_duration' => 120,
        ];

        $connections = [
            'test_with_both' => [
                'hosts'               => ['localhost:9200'],
                'profiling'           => false,
                'logging'             => true,
                'bulk_batch_size'     => 100,
                'http_client_options' => $httpClientOptions,
                'http_client_service' => 'app.symfony_http_client',
            ],
        ];

        $container = new ContainerBuilder();
        $container->setParameter('sfes.connections', $connections);

        $prototypeDefinition = new Definition(ConnectionManager::class);
        $prototypeDefinition->setArguments([null, null, '%kernel.debug%', null]);
        $container->setDefinition('sfes.connection_manager_prototype', $prototypeDefinition);

        $container->setDefinition('event_dispatcher', new Definition());
        $container->setDefinition('logger', new Definition());
        $container->setDefinition('app.symfony_http_client', new Definition());

        $compilerPass = new AddConnectionsPass();
        $compilerPass->process($container);

        $connectionDefinition = $container->getDefinition('sfes.connection.test_with_both');

        // Verify HTTP client service is injected
        // Note: ChildDefinition stores replaced arguments with string keys like "index_3"
        $arguments = $connectionDefinition->getArguments();
        $this->assertArrayHasKey('index_3', $arguments, 'Connection definition should have argument 3 set');
        $httpClientArgument = $connectionDefinition->getArgument(3);
        $this->assertInstanceOf(Reference::class, $httpClientArgument);
        $this->assertSame('app.symfony_http_client', (string) $httpClientArgument);

        // Verify HTTP client options are in settings
        $connectionSettings = $connectionDefinition->getArgument(1);
        $this->assertArrayHasKey('http_client_options', $connectionSettings);
        $this->assertSame($httpClientOptions, $connectionSettings['http_client_options']);
    }

    public function testProcessCreatesDefaultAlias(): void
    {
        $connections = [
            'default' => [
                'hosts'               => ['localhost:9200'],
                'profiling'           => false,
                'logging'             => false,
                'bulk_batch_size'     => 100,
                'http_client_options' => [],
                'http_client_service' => null,
            ],
        ];

        $container = new ContainerBuilder();
        $container->setParameter('sfes.connections', $connections);

        $prototypeDefinition = new Definition(ConnectionManager::class);
        $prototypeDefinition->setArguments([null, null, '%kernel.debug%', null]);
        $container->setDefinition('sfes.connection_manager_prototype', $prototypeDefinition);

        $container->setDefinition('event_dispatcher', new Definition());
        $container->setDefinition('logger', new Definition());

        $compilerPass = new AddConnectionsPass();
        $compilerPass->process($container);

        // Verify the default connection has an alias to ConnectionManager class
        $this->assertTrue($container->hasAlias(ConnectionManager::class));
        $alias = $container->getAlias(ConnectionManager::class);
        $this->assertSame('sfes.connection.default', (string) $alias);
    }
}
