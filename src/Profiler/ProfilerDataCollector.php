<?php

namespace Sineflow\ElasticsearchBundle\Profiler;

use Sineflow\ElasticsearchBundle\Manager\ConnectionManagerRegistry;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\DataCollector\DataCollector;

class ProfilerDataCollector extends DataCollector
{
    /**
     * @var array Registered index managers.
     */
    private array $indexManagers = [];

    public function __construct(
        private readonly ConnectionManagerRegistry $connectionManagerRegistry,
    ) {
        $this->reset();
    }

    public function getIndexManagers(): array
    {
        return $this->data['indexManagers']->getValue();
    }

    public function setIndexManagers(array $indexManagers): void
    {
        foreach ($indexManagers as $name => $manager) {
            $this->indexManagers[$name] = \sprintf('sfes.index.%s', $name);
        }
    }

    public function collect(Request $request, Response $response, ?\Throwable $exception = null): void
    {
        $this->data['indexManagers'] = $this->cloneVar($this->indexManagers);

        foreach ($this->connectionManagerRegistry->getAll() as $connectionManager) {
            $profilerQueryLogCollection = $connectionManager->getProfilerQueryLogCollection();
            if ($profilerQueryLogCollection instanceof ProfilerQueryLogCollection) {
                foreach ($profilerQueryLogCollection->getAll() as $logRecord) {
                    $this->data['queries'][] = $logRecord->asArray();
                    $this->data['totalQueryTime'] += $logRecord->getDuration(); // in milliseconds
                }
                $profilerQueryLogCollection->clear();
            }
        }
    }

    /**
     * Returns total time queries took in ms.
     */
    public function getTotalQueryTime(): float
    {
        return \round($this->data['totalQueryTime'], 2);
    }

    /**
     * Returns number of queries executed.
     */
    public function getQueryCount(): int
    {
        return count($this->data['queries']);
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

    public function reset(): void
    {
        $this->data = [
            'indexManagers'  => [], // The list of all defined index managers
            'totalQueryTime' => .0, // Total time for all queries in ms.
            'queries'        => [], // Array with all the queries
        ];
    }

    public function getName(): string
    {
        return 'sfes.profiler';
    }
}
