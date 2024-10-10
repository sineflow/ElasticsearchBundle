<?php

namespace Sineflow\ElasticsearchBundle\Mapping;

/**
 * DumperInterface is the interface implemented by elasticsearch document annotations.
 */
interface DumperInterface
{
    /**
     * Dumps properties into array.
     *
     * @param array $settings Options to configure dump output
     */
    public function dump(array $settings = []): array;
}
