<?php

namespace Sineflow\ElasticsearchBundle\Tests\Unit\DTO;

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
    public function getLinesProvider()
    {
        return [
            [
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
            ],

            [
                ['create', 'myindex', [], false],
                [
                    [
                        'create' => [
                            '_index' => 'myindex',
                        ],
                    ],
                    [],
                ],
            ],

            [
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
            ],

            [
                ['delete', 'myindex', ['_id' => '3'], false],
                [
                    [
                        'delete' => [
                            '_index' => 'myindex',
                            '_id'    => 3,
                        ],
                    ],
                ],
            ],

        ];
    }

    /**
     * @param array $input
     * @param array $expected
     *
     * @dataProvider getLinesProvider
     */
    public function testGetLines($input, $expected)
    {
        $bqi = new BulkQueryItem($input[0], $input[1], $input[2]);
        $lines = $bqi->getLines($input[3]);
        $this->assertEquals($expected, $lines);
    }
}
