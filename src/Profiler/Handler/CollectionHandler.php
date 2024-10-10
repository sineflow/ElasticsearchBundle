<?php

namespace Sineflow\ElasticsearchBundle\Profiler\Handler;

use Monolog\Handler\AbstractProcessingHandler;
use Monolog\LogRecord;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;

/**
 * Handler that saves all records to itself.
 */
class CollectionHandler extends AbstractProcessingHandler
{
    private array $records = [];

    public function __construct(private readonly RequestStack $requestStack, private readonly bool $backtraceEnabled = false)
    {
    }

    /**
     * {@inheritdoc}
     *
     * NOTE: The array typehint of $record is only for BC with Monolog 2.*
     */
    protected function write(LogRecord|array $record): void
    {
        $request = $this->requestStack->getCurrentRequest();
        if ($request instanceof Request) {
            $record['extra']['requestUri'] = $request->getRequestUri();
            $record['extra']['route'] = $request->attributes->get('_route');
        }

        $record['extra']['backtrace'] = null;
        if ($this->backtraceEnabled && !empty($record['context'])) {
            $record['extra']['backtrace'] = \debug_backtrace(\DEBUG_BACKTRACE_IGNORE_ARGS);
        }

        $this->records[] = $record;
    }

    /**
     * Returns recorded data.
     *
     * @return LogRecord[]
     */
    public function getRecords(): array
    {
        return $this->records;
    }

    /**
     * Clears recorded data.
     */
    public function clearRecords(): void
    {
        $this->records = [];
    }
}
