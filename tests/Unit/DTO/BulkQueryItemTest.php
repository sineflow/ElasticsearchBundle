<?php

namespace Sineflow\ElasticsearchBundle\Tests\Unit\DTO;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Sineflow\ElasticsearchBundle\DTO\BulkQueryItem;

/**
 * Class BulkQueryItemTest
 */
class BulkQueryItemTest extends TestCase
{
    /**
     * @return array
     */
    public static function getLinesProvider(): \Iterator
    {
        yield [
            ['index', 'myindex', ['_id' => '3', 'foo' => 'bar'], false],
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
            ['create', 'myindex', [], false],
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
            ['delete', 'myindex', ['_id' => '3'], false],
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
