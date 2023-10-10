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
    public const UNDEFINED_ROUTE = 'undefined_route';

    /**
     * @var Logger[] Watched loggers.
     */
    private $loggers = [];

    /**
     * @var array Registered index managers.
     */
    private $indexManagers = [];

    /**
     * ElasticsearchProfiler constructor.
     */
    public function __construct()
    {
        $this->reset();
    }

    /**
     * Adds logger to look for collector handler.
     */
    public function addLogger(Logger $logger)
    {
        $this->loggers[] = $logger;
    }

    /**
     * {@inheritDoc}
     */
    public function collect(Request $request, Response $response, \Throwable $exception = null)
    {
        $this->data['indexManagers'] = $this->cloneVar($this->indexManagers);

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
        $this->data = [
            'indexManagers' => [], // The list of all defined index managers
            'queryCount'    => 0,  // The queries count
            'time'          => .0, // Total time for all queries in ms.
            'queries'       => [], // Array with all the queries
        ];
    }

    /**
     * Returns total time queries took.
     */
    public function getTime(): float
    {
        return \round($this->data['time'] * 1000, 2);
    }

    /**
     * Returns number of queries executed.
     */
    public function getQueryCount(): int
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
     */
    public function getQueries(): array
    {
        return $this->data['queries'];
    }

    public function getIndexManagers(): array
    {
        return $this->data['indexManagers']->getValue();
    }

    public function setIndexManagers(array $indexManagers)
    {
        foreach ($indexManagers as $name => $manager) {
            $this->indexManagers[$name] = \sprintf('sfes.index.%s', $name);
        }
    }

    /**
     * {@inheritdoc}
     */
    public function getName(): string
    {
        return 'sfes.profiler';
    }

    /**
     * Handles passed records.
     */
    private function handleRecords(array $records)
    {
        $this->data['queryCount'] += \count($records) / 2;
        $queryBody = '';
        $rawRequest = '';
        foreach ($records as $record) {
            // First record will never have context.
            if (!empty($record['context'])) {
                $this->data['time'] += $record['context']['duration'];
                $route = !empty($record['extra']['route']) ? $record['extra']['route'] : self::UNDEFINED_ROUTE;
                $this->addQuery($route, $record, $queryBody, $rawRequest);
            } else {
                $position = \strpos($record['message'], ' -d');
                $queryBody = false !== $position ? \substr($record['message'], $position + 3) : '';
                $rawRequest = $record['message'];
            }
        }
    }

    private function addQuery(string $route, array $record, string $queryBody, string $rawRequest)
    {
        $parsedUrl = \array_merge(
            [
                'scheme' => '',
                'host'   => '',
                'port'   => '',
                'path'   => '',
                'query'  => '',
            ],
            \parse_url($record['context']['uri'])
        );
        $senseRequest = $record['context']['method'].' '.$parsedUrl['path'];
        if ($parsedUrl['query']) {
            $senseRequest .= '?'.$parsedUrl['query'];
        }
        if ($queryBody) {
            $senseRequest .= "\n".\trim($queryBody, " '");
        }

        $this->data['queries'][$route][] = \array_merge(
            [
                'time'         => $record['context']['duration'] * 1000,
                'curlRequest'  => $rawRequest,
                'senseRequest' => $senseRequest,
                'backtrace'    => $record['extra']['backtrace'],
            ],
            \array_diff_key(\parse_url($record['context']['uri']), \array_flip(['query']))
        );
    }
}
