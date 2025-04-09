<?php

namespace Sineflow\ElasticsearchBundle\Profiler;

final class ProfilerQueryLogCollection
{
    /**
     * @var ProfilerQueryLogRecord[]
     */
    private array $records = [];

    public function addRecord(ProfilerQueryLogRecord $record): void
    {
        $this->records[] = $record;
    }

    public function getAll(): array
    {
        return $this->records;
    }

    public function clear(): void
    {
        $this->records = [];
    }
}
