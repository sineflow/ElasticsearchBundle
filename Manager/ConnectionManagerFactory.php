<?php

namespace Sineflow\ElasticsearchBundle\Manager;

use Elasticsearch\ClientBuilder;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;

/**
 * Elasticsearch connection factory class
 */
class ConnectionManagerFactory
{
    /**
     * @var LoggerInterface
     */
    private $logger;

    /**
     * @var LoggerInterface
     */
    private $tracer;

    /**
     * @var boolean
     */
    private $kernelDebug;

    /**
     * @var EventDispatcherInterface
     */
    private $eventDispatcher;

    /**
     * @param boolean         $kernelDebug
     * @param LoggerInterface $tracer
     * @param LoggerInterface $logger
     */
    public function __construct($kernelDebug, LoggerInterface $tracer = null, LoggerInterface $logger = null)
    {
        $this->kernelDebug = $kernelDebug;
        $this->tracer = $tracer;
        $this->logger = $logger;
    }

    /**
     * @return EventDispatcherInterface
     */
    public function getEventDispatcher()
    {
        return $this->eventDispatcher;
    }

    /**
     * @param EventDispatcherInterface $eventDispatcher
     */
    public function setEventDispatcher($eventDispatcher)
    {
        $this->eventDispatcher = $eventDispatcher;
    }

    /**
     * @param string $connectionName
     * @param array  $connectionSettings
     * @return ConnectionManager
     */
    public function createConnectionManager($connectionName, $connectionSettings)
    {
        $clientBuilder = ClientBuilder::create();

        $clientBuilder->setHosts($connectionSettings['hosts']);

        if ($this->tracer && $connectionSettings['profiling'] && $this->kernelDebug) {
            $clientBuilder->setTracer($this->tracer);
        }

        if ($this->logger && $connectionSettings['logging']) {
            $clientBuilder->setLogger($this->logger);
        }

        $connectionManager = new ConnectionManager(
            $connectionName,
            $clientBuilder->build(),
            $connectionSettings
        );

        $connectionManager->setLogger($this->logger ?: new NullLogger());
        $connectionManager->setEventDispatcher($this->eventDispatcher);

        return $connectionManager;
    }
}
