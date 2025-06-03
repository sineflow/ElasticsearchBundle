<?php

namespace Sineflow\ElasticsearchBundle\Tests\Unit\Mapping;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Sineflow\ElasticsearchBundle\Mapping\Caser;

class CaserTest extends TestCase
{
    public static function providerForCamel(): array
    {
        $out = [
            ['foo_bar', 'fooBar'],
            ['_foo-bar', 'fooBar'],
            ['Fo0bAr', 'fo0bAr'],
            ['_f$oo^ba&r_', 'f$oo^ba&r'],
            [23456, '23456'],
        ];

        return $out;
    }

    public static function providerForSnake(): array
    {
        $out = [
            ['FooBar', 'foo_bar'],
            ['_foo-bar', 'foo_bar'],
            ['Fo0bAr', 'fo0b_ar'],
            [23456, '23456'],
        ];

        return $out;
    }

    /**
     * @param string $input
     * @param string $expected
     */
    #[DataProvider('providerForCamel')]
    public function testCamel($input, $expected): void
    {
        $this->assertSame($expected, Caser::camel($input));
    }

    /**
     * @param string $input
     * @param string $expected
     */
    #[DataProvider('providerForSnake')]
    public function testSnake($input, $expected): void
    {
        $this->assertSame($expected, Caser::snake($input));
    }
}
