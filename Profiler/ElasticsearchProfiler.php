<?php

namespace Sineflow\ElasticsearchBundle\Profiler;

use Monolog\Logger;
use Sineflow\ElasticsearchBundle\Profiler\Handler\CollectionHandler;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\DataCollector\DataCollectorInterface;

/**
 * Data collector for profiling elasticsearch bundle.
 */
class ElasticsearchProfiler implements DataCollectorInterface
{
    const UNDEFINED_ROUTE = 'undefined_route';

    /**
     * @var Logger[] Watched loggers.
     */
    private $loggers = [];

    /**
     * @var array Queries array.
     */
    private $queries = [];

    /**
     * @var int Query count.
     */
    private $count = 0;

    /**
     * @var float Time all queries took in ms.
     */
    private $time = .0;

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
     * {@inheritdoc}
     */
    public function collect(Request $request, Response $response, \Exception $exception = null)
    {
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
     * Returns total time queries took.
     *
     * @return string
     */
    public function getTime()
    {
        return round($this->time * 1000, 2);
    }

    /**
     * Returns number of queries executed.
     *
     * @return int
     */
    public function getQueryCount()
    {
        return $this->count;
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
        return $this->queries;
    }

    /**
     * @return array
     */
    public function getIndexManagers()
    {
        return $this->indexManagers;
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
     * @param array  $records
     */
    private function handleRecords($records)
    {
        $this->count += count($records) / 2;
        $queryBody = '';
        $rawRequest = '';
        foreach ($records as $record) {
            // First record will never have context.
            if (!empty($record['context'])) {
                $this->time += $record['context']['duration'];
                $route = !empty($record['extra']['route']) ? $record['extra']['route'] : self::UNDEFINED_ROUTE;
                $this->addQuery($route, $record, $queryBody, $rawRequest);
            } else {
                $position = strpos($record['message'], ' -d');
                $queryBody = $position !== false ? substr($record['message'], $position + 3) : '';
                $rawRequest = $record['message'];
            }
        }
    }

    private function addQuery($route, $record, $queryBody, $rawRequest)
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

        $this->queries[$route][] = array_merge(
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
