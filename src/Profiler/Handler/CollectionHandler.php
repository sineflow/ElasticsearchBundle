<?php

namespace Sineflow\ElasticsearchBundle\Profiler\Handler;

use Monolog\Handler\AbstractProcessingHandler;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;

/**
 * Handler that saves all records to itself.
 */
class CollectionHandler extends AbstractProcessingHandler
{
    /**
     * @var array
     */
    private $records = [];

    /**
     * @var RequestStack
     */
    private $requestStack;

    /**
     * @var bool
     */
    private $backtraceEnabled;

    /**
     * @param RequestStack $requestStack
     * @param bool         $backtraceEnabled
     */
    public function __construct(RequestStack $requestStack, $backtraceEnabled = false)
    {
        $this->requestStack = $requestStack;
        $this->backtraceEnabled = $backtraceEnabled;
    }

    /**
     * {@inheritdoc}
     */
    protected function write(array $record)
    {
        $request = $this->requestStack->getCurrentRequest();
        if ($request instanceof Request) {
            $record['extra']['requestUri'] = $request->getRequestUri();
            $record['extra']['route'] = $request->attributes->get('_route');
        }

        $record['extra']['backtrace'] = null;
        if ($this->backtraceEnabled && !empty($record['context'])) {
            $record['extra']['backtrace'] = debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS);
        }

        $this->records[] = $record;
    }

    /**
     * Returns recorded data.
     *
     * @return array
     */
    public function getRecords()
    {
        return $this->records;
    }

    /**
     * Clears recorded data.
     */
    public function clearRecords()
    {
        $this->records = [];
    }
}
