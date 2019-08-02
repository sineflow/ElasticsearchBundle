<?php

namespace Sineflow\ElasticsearchBundle\Manager;

use Elasticsearch\Client;
use Elasticsearch\Common\Exceptions\InvalidArgumentException;
use Psr\Log\LoggerInterface;
use Sineflow\ElasticsearchBundle\DTO\BulkQueryItem;
use Sineflow\ElasticsearchBundle\Event\Events;
use Sineflow\ElasticsearchBundle\Event\PostCommitEvent;
use Sineflow\ElasticsearchBundle\Exception\BulkRequestException;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;

/**
 * This class interacts with elasticsearch using injected client.
 */
class ConnectionManager
{
    /**
     * @var string The unique connection manager name (the key from the index configuration)
     */
    private $connectionName;

    /**
     * @var Client
     */
    private $client;

    /**
     * @var array
     */
    private $connectionSettings;

    /**
     * @var BulkQueryItem[] Container for bulk queries.
     */
    private $bulkQueries;

    /**
     * @var array Holder for consistency, refresh and replication parameters.
     */
    private $bulkParams;

    /**
     * @var LoggerInterface
     */
    private $logger = null;

    /**
     * @var bool
     */
    private $autocommit;

    /**
     * @var EventDispatcherInterface
     */
    private $eventDispatcher;

    /**
     * Construct.
     *
     * @param string $connectionName     The unique connection name
     * @param Client $client             Elasticsearch client.
     * @param array  $connectionSettings Settings array.
     */
    public function __construct($connectionName, Client $client, $connectionSettings)
    {
        $this->connectionName = $connectionName;
        $this->client = $client;
        $this->connectionSettings = $connectionSettings;
        $this->bulkQueries = [];
        $this->bulkParams = [];
        $this->autocommit = false;
    }

    /**
     * @param LoggerInterface $logger
     */
    public function setLogger(LoggerInterface $logger)
    {
        $this->logger = $logger;
    }

    /**
     * @return LoggerInterface|null
     */
    public function getLogger()
    {
        return $this->logger;
    }

    /**
     * @return string
     */
    public function getConnectionName()
    {
        return $this->connectionName;
    }

    /**
     * @return Client
     */
    public function getClient()
    {
        return $this->client;
    }

    /**
     * @return array
     */
    public function getConnectionSettings()
    {
        return $this->connectionSettings;
    }

    /**
     * @return bool
     */
    public function isAutocommit()
    {
        return $this->autocommit;
    }

    /**
     * @param bool $autocommit
     */
    public function setAutocommit($autocommit)
    {
        // If the autocommit mode is being turned on, commit any pending bulk items
        if (!$this->autocommit && $autocommit) {
            $this->commit();
        }

        $this->autocommit = $autocommit;
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
     * Adds query to bulk queries container.
     *
     * @param string $operation  One of: index, update, delete, create.
     * @param string $index      Elasticsearch index name.
     * @param array  $query      Bulk item query (aka optional_source in the ES docs)
     * @param array  $metaParams Additional meta data params for the bulk item
     */
    public function addBulkOperation($operation, $index, array $query, array $metaParams = [])
    {
        $this->bulkQueries[] = new BulkQueryItem($operation, $index, $query, $metaParams);
    }

    /**
     * Returns the number of bulk operations currently queued
     *
     * @return int
     */
    public function getBulkOperationsCount()
    {
        return count($this->bulkQueries);
    }

    /**
     * Optional setter to change bulk query params.
     *
     * @param array $params Possible keys:
     *                      ['consistency'] = (enum) Explicit write consistency setting for the operation.
     *                      ['refresh']     = (boolean) Refresh the index after performing the operation.
     *                      ['replication'] = (enum) Explicitly set the replication type.
     */
    public function setBulkParams(array $params)
    {
        $this->bulkParams = $params;
    }

    /**
     * Executes the accumulated bulk queries to the index.
     *
     * @param bool $forceRefresh Make new data available for searching immediately
     *                           If immediate availability of the data for searching is not crucial, it's better
     *                           to set this to false, to get better performance. In the latter case, data would be
     *                           normally available within 1 second
     *
     * @throws BulkRequestException
     */
    public function commit($forceRefresh = true)
    {
        if (empty($this->bulkQueries)) {
            return;
        }

        $bulkRequest = $this->getBulkRequest();

        $response = $this->getClient()->bulk($bulkRequest);
        if ($forceRefresh) {
            $this->refresh();
        }

        $this->bulkQueries = [];

        if ($response['errors']) {
            $errorCount = $this->logBulkRequestErrors($response['items']);
            $e = new BulkRequestException(sprintf('Bulk request failed with %s error(s)', $errorCount));
            $e->setBulkResponseItems($response['items'], $bulkRequest);
            throw $e;
        }

        if ($this->eventDispatcher) {
            $this->eventDispatcher->dispatch(Events::POST_COMMIT, new PostCommitEvent($response, $this));
        }
    }

    /**
     * Get the current bulk request queued for commit
     *
     * @return array
     */
    private function getBulkRequest()
    {
        // Go through each bulk query item
        $bulkRequest = [];
        $cachedAliasIndices = [];
        foreach ($this->bulkQueries as $bulkQueryItem) {
            if (isset($cachedAliasIndices[$bulkQueryItem->getIndex()])) {
                $indices = $cachedAliasIndices[$bulkQueryItem->getIndex()];
            } else {
                // Check whether the target index is actually an alias pointing to more than one index
                // in which case, two separate bulk query operation will be set for each physical index
                $indices = array_keys($this->getClient()->indices()->getAlias(['index' => $bulkQueryItem->getIndex()]));
                $cachedAliasIndices[$bulkQueryItem->getIndex()] = $indices;
            }
            foreach ($indices as $index) {
                foreach ($bulkQueryItem->getLines($index) as $bulkQueryLine) {
                    $bulkRequest['body'][] = $bulkQueryLine;
                }
            }
        }

        $bulkRequest = array_merge($bulkRequest, $this->bulkParams);

        return $bulkRequest;
    }

    /**
     * Logs errors from a bulk request and return their count
     *
     * @param array $responseItems bulk response items
     *
     * @return int The errors count
     */
    private function logBulkRequestErrors($responseItems)
    {
        $errorsCount = 0;
        foreach ($responseItems as $responseItem) {
            // Get the first element of the response item (its key could be one of index/create/delete/update)
            $action = key($responseItem);
            $actionResult = reset($responseItem);

            // If there was an error on that item
            if (!empty($actionResult['error'])) {
                $errorsCount++;
                $this->logger->error(sprintf('Bulk %s item failed', $action), $actionResult);
            }
        }

        return $errorsCount;
    }

    /**
     * Send refresh call to index.
     *
     * Makes your documents available for search.
     */
    public function refresh()
    {
        $this->getClient()->indices()->refresh();
    }

    /**
     * Send flush call to index.
     *
     * Causes a Lucene commit to happen
     * In most cases refresh() should be used instead, as this is a very expensive operation
     */
    public function flush()
    {
        $this->getClient()->indices()->flush();
    }

    /**
     * Return all defined aliases in the ES cluster with all indices they point to
     *
     * @return array The ES aliases
     */
    public function getAliases()
    {
        $aliases = [];
        // Get all indices and their linked aliases and invert the results
        $indices = $this->getClient()->indices()->getAliases();
        foreach ($indices as $index => $data) {
            foreach ($data['aliases'] as $alias => $aliasData) {
                $aliases[$alias][$index] = [];
            }
        }

        return $aliases;
    }

    /**
     * Check whether all of the specified indexes/aliases exist in the ES server
     *
     * NOTE: This is a workaround function to the native indices()->exists() function of the ES client
     * because the latter generates warnings in the log file when index/alias does not exist
     * @see https://github.com/elasticsearch/elasticsearch-php/issues/163
     *
     * $params['index'] = (list) A comma-separated list of indices/aliases to check (Required)
     * @param array $params Associative array of parameters
     *
     * @return bool
     *
     * @throws InvalidArgumentException
     */
    public function existsIndexOrAlias(array $params)
    {
        if (!isset($params['index'])) {
            throw new InvalidArgumentException('Required parameter "index" missing');
        }

        $indicesAndAliasesToCheck = array_flip(explode(',', $params['index']));

        // Get all available indices with their aliases
        $allAliases = $this->getClient()->indices()->getAliases();
        foreach ($allAliases as $index => $data) {
            if (isset($indicesAndAliasesToCheck[$index])) {
                unset($indicesAndAliasesToCheck[$index]);
            }
            foreach ($data['aliases'] as $alias => $_nothing) {
                if (isset($indicesAndAliasesToCheck[$alias])) {
                    unset($indicesAndAliasesToCheck[$alias]);
                }
            }
            if (empty($indicesAndAliasesToCheck)) {
                return true;
            }
        }

        return false;
    }


    /**
     * Check whether any of the specified index aliases exists in the ES server
     *
     * NOTE: This is a workaround function to the native indices()->existsAlias() function of the ES client
     * because the latter generates warnings in the log file when alias does not exists
     * When this is fixed, we should revert back to using the ES client's function, not this one
     * @see https://github.com/elasticsearch/elasticsearch-php/issues/163
     *
     * @param array $params
     * $params['name']               = (list) A comma-separated list of alias names to return (Required)
     *
     * @return bool
     *
     * @throws InvalidArgumentException
     */
    public function existsAlias(array $params)
    {
        if (!isset($params['name'])) {
            throw new InvalidArgumentException('Required parameter "name" missing');
        }

        $aliasesToCheck = explode(',', $params['name']);

        // Get all available indexes with their aliases
        $allAliases = $this->getClient()->indices()->getAliases();
        foreach ($allAliases as $index => $data) {
            foreach ($aliasesToCheck as $aliasToCheck) {
                if (isset($data['aliases'][$aliasToCheck])) {
                    return true;
                }
            }
        }

        return false;
    }
}
