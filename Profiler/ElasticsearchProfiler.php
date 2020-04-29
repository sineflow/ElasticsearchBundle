<?php

namespace Sineflow\ElasticsearchBundle\Profiler;

use Monolog\Logger;
use Sineflow\ElasticsearchBundle\Profiler\Handler\CollectionHandler;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\DataCollector\DataCollector;

/**
 * Data collector for profiling elasticsearch bundle.
 */
class ElasticsearchProfiler extends DataCollector
{
    const UNDEFINED_ROUTE = 'undefined_route';

    /**
     * @var Logger[] Watched loggers.
     */
    private $loggers = [];

    /**
     * @var array Registered index managers.
     */
    private $indexManagers = [];

    /**
     * Adds logger to look for collector handler.
     *
     * @param Logger $logger
     */
    public function addLogger(Logger $logger)
    {
        $this->loggers[] = $logger;
    }

    /**
     * {@inheritDoc}
     */
    public function collect(Request $request, Response $response, \Exception $exception = null)
    {
        $this->data['indexManagers'] = $this->cloneVar($this->indexManagers);
        $this->data['queryCount'] = 0;  // The queries count
        $this->data['time'] = .0;       // Total time for all queries in ms.
        $this->data['queries'] = [];    // Array with all the queries

        /** @var Logger $logger */
        foreach ($this->loggers as $logger) {
            foreach ($logger->getHandlers() as $handler) {
                if ($handler instanceof CollectionHandler) {
                    $this->handleRecords($handler->getRecords());
                    $handler->clearRecords();
                }
            }
        }
    }

    /**
     * {@inheritDoc}
     */
    public function reset()
    {
        $this->data = [];
    }

    /**
     * Returns total time queries took.
     *
     * @return string
     */
    public function getTime()
    {
        return round($this->data['time'] * 1000, 2);
    }

    /**
     * Returns number of queries executed.
     *
     * @return int
     */
    public function getQueryCount()
    {
        return $this->data['queryCount'];
    }

    /**
     * Returns information about executed queries.
     *
     * Eg. keys:
     *      'body'    - Request body.
     *      'method'  - HTTP method.
     *      'uri'     - Uri request was sent.
     *      'time'    - Time client took to respond.
     *
     * @return array
     */
    public function getQueries()
    {
        return $this->data['queries'];
    }

    /**
     * @return array
     */
    public function getIndexManagers()
    {
        return $this->data['indexManagers']->getValue();
    }

    /**
     * @param array $indexManagers
     */
    public function setIndexManagers($indexManagers)
    {
        foreach ($indexManagers as $name => $manager) {
            $this->indexManagers[$name] = sprintf('sfes.index.%s', $name);
        }
    }

    /**
     * {@inheritdoc}
     */
    public function getName()
    {
        return 'sfes.profiler';
    }

    /**
     * Handles passed records.
     *
     * @param array $records
     */
    private function handleRecords($records)
    {
        $this->data['queryCount'] += count($records) / 2;
        $queryBody = '';
        $rawRequest = '';
        foreach ($records as $record) {
            // First record will never have context.
            if (!empty($record['context'])) {
                $this->data['time'] += $record['context']['duration'];
                $route = !empty($record['extra']['route']) ? $record['extra']['route'] : self::UNDEFINED_ROUTE;
                $this->addQuery($route, $record, $queryBody, $rawRequest);
            } else {
                $position = strpos($record['message'], ' -d');
                $queryBody = $position !== false ? substr($record['message'], $position + 3) : '';
                $rawRequest = $record['message'];
            }
        }
    }

    /**
     * @param string $route
     * @param array  $record
     * @param string $queryBody
     * @param string $rawRequest
     */
    private function addQuery(string $route, array $record, string $queryBody, string $rawRequest)
    {
        $parsedUrl = array_merge(
            [
                'scheme' => '',
                'host' => '',
                'port' => '',
                'path' => '',
                'query' => '',
            ],
            parse_url($record['context']['uri'])
        );
        $senseRequest = $record['context']['method'].' '.$parsedUrl['path'];
        if ($parsedUrl['query']) {
            $senseRequest .= '?'.$parsedUrl['query'];
        }
        if ($queryBody) {
            $senseRequest .= "\n".trim($queryBody, " '");
        }

        $this->data['queries'][$route][] = array_merge(
            [
                'time' => $record['context']['duration'] * 1000,
                'curlRequest' => $rawRequest,
                'senseRequest' => $senseRequest,
                'backtrace' => $record['extra']['backtrace'],
            ],
            array_diff_key(parse_url($record['context']['uri']), array_flip(['query']))
        );
    }
}
