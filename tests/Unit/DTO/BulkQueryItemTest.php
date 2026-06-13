<?php

declare(strict_types=1);

namespace Sineflow\ElasticsearchBundle\Tests\Unit\DTO;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Sineflow\ElasticsearchBundle\DTO\BulkQueryItem;

/**
 * Class BulkQueryItemTest
 */
final class BulkQueryItemTest extends TestCase
{
    /**
     * @return array
     */
    public static function getLinesProvider(): \Iterator
    {
        yield [
            ['index', 'myindex', ['_id' => '3', 'foo' => 'bar'], null],
            [
                [
                    'index' => [
                        '_index' => 'myindex',
                        '_id'    => 3,
                    ],
                ],
                [
                    'foo' => 'bar',
                ],
            ],
        ];
        yield [
            ['create', 'myindex', [], null],
            [
                [
                    'create' => [
                        '_index' => 'myindex',
                    ],
                ],
                [],
            ],
        ];
        yield [
            ['update', 'myindex', ['_id' => '3'], 'forcedindex'],
            [
                [
                    'update' => [
                        '_index' => 'forcedindex',
                        '_id'    => 3,
                    ],
                ],
                [],
            ],
        ];
        yield [
            ['delete', 'myindex', ['_id' => '3'], null],
            [
                [
                    'delete' => [
                        '_index' => 'myindex',
                        '_id'    => 3,
                    ],
                ],
            ],
        ];
    }

    /**
     * @param array $input
     * @param array $expected
     */
    #[DataProvider('getLinesProvider')]
    public function testGetLines($input, $expected): void
    {
        $bqi = new BulkQueryItem($input[0], $input[1], $input[2]);
        $lines = $bqi->getLines($input[3]);
        $this->assertEquals($expected, $lines);
    }
}
