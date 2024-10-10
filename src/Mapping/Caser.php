<?php

namespace Sineflow\ElasticsearchBundle\Mapping;

/**
 * Utility for string case transformations.
 */
class Caser
{
    /**
     * Transforms string to camel case.
     */
    public static function camel(string $string): string
    {
        return \lcfirst(\str_replace([' ', '_', '-'], '', \ucwords($string, ' _-')));
    }

    /**
     * Transforms string to snake case.
     */
    public static function snake(string $string): string
    {
        $string = \preg_replace('#([A-Z\d]+)([A-Z][a-z])#', '\1_\2', self::camel($string));
        $string = \preg_replace('#([a-z\d])([A-Z])#', '\1_\2', (string) $string);

        return \strtolower(\strtr($string, '-', '_'));
    }
}
