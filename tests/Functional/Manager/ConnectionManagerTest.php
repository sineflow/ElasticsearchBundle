<?php

declare(strict_types=1);

namespace Sineflow\ElasticsearchBundle\Tests\Functional\Manager;

use Psr\Http\Client\ClientInterface;
use Sineflow\ElasticsearchBundle\Manager\ConnectionManager;
use Sineflow\ElasticsearchBundle\Tests\AbstractContainerAwareTestCase;

final class ConnectionManagerTest extends AbstractContainerAwareTestCase
{
    public function testConnectionManagerWithoutHttpClientService(): void
    {
        $connectionManager = $this->getContainer()->get('sfes.connection.default');

        $this->assertInstanceOf(ConnectionManager::class, $connectionManager);

        // Use reflection to verify HTTP client is null (will use auto-discovery)
        $reflection = new \ReflectionClass($connectionManager);
        $httpClientProperty = $reflection->getProperty('httpClient');

        $this->assertNull($httpClientProperty->getValue($connectionManager), 'Default connection should not have a custom HTTP client injected');
    }

    public function testConnectionManagerHasHttpClientOptions(): void
    {
        $connectionManager = $this->getContainer()->get('sfes.connection.default');

        $this->assertInstanceOf(ConnectionManager::class, $connectionManager);

        // Use reflection to access connection settings
        $reflection = new \ReflectionClass($connectionManager);
        $settingsProperty = $reflection->getProperty('connectionSettings');

        $settings = $settingsProperty->getValue($connectionManager);

        $this->assertIsArray($settings);
        $this->assertArrayHasKey('http_client_options', $settings, 'Connection settings should contain http_client_options key');
        $this->assertIsArray($settings['http_client_options'], 'http_client_options should be an array');
    }

    public function testConnectionManagerWithCustomHttpClientService(): void
    {
        // Create a mock HTTP client
        $mockHttpClient = $this->createStub(ClientInterface::class);

        // Register it as a service
        $container = $this->getContainer();
        $container->set('app.test_http_client', $mockHttpClient);

        // Create a ConnectionManager with the custom HTTP client
        $connectionManager = new ConnectionManager(
            'test_custom_client',
            [
                'hosts'               => ['localhost:9200'],
                'profiling'           => false,
                'logging'             => false,
                'bulk_batch_size'     => 100,
                'http_client_options' => [
                    'timeout'      => 45,
                    'max_duration' => 600,
                ],
                'http_client_service' => 'app.test_http_client',
            ],
            true, // debug mode
            $mockHttpClient
        );

        // Use reflection to verify the HTTP client is set
        $reflection = new \ReflectionClass($connectionManager);
        $httpClientProperty = $reflection->getProperty('httpClient');

        $injectedClient = $httpClientProperty->getValue($connectionManager);
        $this->assertSame($mockHttpClient, $injectedClient, 'Custom HTTP client should be injected into ConnectionManager');
    }

    public function testConnectionManagerPreservesHttpClientOptions(): void
    {
        $customOptions = [
            'timeout'         => 30,
            'connect_timeout' => 10,
            'max_duration'    => 3600,
            'extra'           => [
                'curl' => [
                    CURLOPT_SSL_VERIFYPEER => false,
                ],
            ],
        ];

        $connectionManager = new ConnectionManager(
            'test_options',
            [
                'hosts'               => ['localhost:9200'],
                'profiling'           => false,
                'logging'             => false,
                'bulk_batch_size'     => 100,
                'http_client_options' => $customOptions,
                'http_client_service' => null,
            ],
            true,
            null
        );

        // Use reflection to access connection settings
        $reflection = new \ReflectionClass($connectionManager);
        $settingsProperty = $reflection->getProperty('connectionSettings');

        $settings = $settingsProperty->getValue($connectionManager);

        $this->assertArrayHasKey('http_client_options', $settings);
        $this->assertSame($customOptions, $settings['http_client_options'], 'HTTP client options should be preserved exactly as configured');

        // Verify nested options are preserved
        $this->assertIsArray($settings['http_client_options']['extra']);
        $this->assertIsArray($settings['http_client_options']['extra']['curl']);
        $this->assertFalse($settings['http_client_options']['extra']['curl'][CURLOPT_SSL_VERIFYPEER]);
    }

    public function testConnectionManagerWithBothHttpClientServiceAndOptions(): void
    {
        $mockHttpClient = $this->createStub(ClientInterface::class);

        $customOptions = [
            'timeout'      => 60,
            'max_duration' => 120,
        ];

        $connectionManager = new ConnectionManager(
            'test_both',
            [
                'hosts'               => ['localhost:9200'],
                'profiling'           => false,
                'logging'             => false,
                'bulk_batch_size'     => 100,
                'http_client_options' => $customOptions,
                'http_client_service' => 'app.test_http_client',
            ],
            true,
            $mockHttpClient
        );

        // Verify HTTP client is injected
        $reflection = new \ReflectionClass($connectionManager);
        $httpClientProperty = $reflection->getProperty('httpClient');

        $injectedClient = $httpClientProperty->getValue($connectionManager);
        $this->assertSame($mockHttpClient, $injectedClient);

        // Verify HTTP client options are preserved
        $settingsProperty = $reflection->getProperty('connectionSettings');

        $settings = $settingsProperty->getValue($connectionManager);
        $this->assertSame($customOptions, $settings['http_client_options']);
    }
}
