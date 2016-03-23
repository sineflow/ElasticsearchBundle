<?php

namespace Sineflow\ElasticsearchBundle\Tests\Functional\Finder\Adapter;

use Knp\Bundle\PaginatorBundle\Pagination\SlidingPagination;
use Sineflow\ElasticsearchBundle\Document\Repository\Repository;
use Sineflow\ElasticsearchBundle\Finder\Finder;
use Sineflow\ElasticsearchBundle\Finder\Adapter\KnpPaginatorAdapter;
use Sineflow\ElasticsearchBundle\Result\DocumentIterator;
use Sineflow\ElasticsearchBundle\Tests\AbstractElasticsearchTestCase;
use Sineflow\ElasticsearchBundle\Tests\app\fixture\Acme\BarBundle\Document\Product;

class KnpPaginatorAdapterTest extends AbstractElasticsearchTestCase
{
    /**
     * {@inheritdoc}
     */
    protected function getDataArray()
    {
        return [
            'bar' => [
                'AcmeBarBundle:Product' => [
                    [
                        '_id' => '1',
                        'title' => 'Foo Product',
                        'category' => [
                            'title' => 'Bar',
                            'tags' => [
                                ['tagname' => 'first tag'],
                                ['tagname' => 'second tag']
                            ]
                        ],
                        'related_categories' => [
                            [
                                'title' => 'Acme',
                                'tags' => [
                                    ['tagname' => 'tutu']
                                ]
                            ],
                            [
                                'title' => 'Doodle',
                            ],
                        ],
                        'ml_info-en' => 'info in English',
                        'ml_info-fr' => 'info in French',
                    ],
                    [
                        '_id' => '2',
                        'title' => 'Bar Product',
                        'category' => null,
                        'related_categories' => [
                            [
                                'title' => 'Acme',
                            ],
                            [
                                'title' => 'Bar',
                            ],
                        ],
                    ],
                    [
                        '_id' => '3',
                        'title' => '3rd Product',
                        'related_categories' => [],
                    ],
                    [
                        '_id' => '54321',
                    ]
                ],
            ],
        ];
    }

    public function testPagination()
    {
        /** @var Repository $repo */
        $repo = $this->getIndexManager('bar')->getRepository('AcmeBarBundle:Product');
        $paginator = $this->getContainer()->get('knp_paginator');

        $query = ['query' => ['match_all' => []], 'sort' => ['_uid' => ['order' =>'asc']]];

        // Test object results

        /** @var KnpPaginatorAdapter $adapter */
        $adapter = $repo->find($query, Finder::RESULTS_OBJECT | Finder::ADAPTER_KNP);

        /** @var SlidingPagination $pagination */
        $pagination = $paginator->paginate($adapter, 2, 2);

        $this->assertCount(2, $pagination);
        $this->assertInstanceOf(Product::class, $pagination->offsetGet(0));
        $this->assertEquals(3, $pagination->offsetGet(0)->id);

        // Test array results

        /** @var KnpPaginatorAdapter $adapter */
        $adapter = $repo->find($query, Finder::RESULTS_ARRAY | Finder::ADAPTER_KNP);

        /** @var SlidingPagination $pagination */
        $pagination = $paginator->paginate($adapter, 2, 2);

        $this->assertEquals(3, $pagination->key());
        $this->assertEquals('3rd Product', $pagination->current()['title']);

        // Test raw results

        /** @var KnpPaginatorAdapter $adapter */
        $adapter = $repo->find($query, Finder::RESULTS_RAW | Finder::ADAPTER_KNP);

        /** @var SlidingPagination $pagination */
        $pagination = $paginator->paginate($adapter, 2, 2);

        $this->assertEquals(3, $pagination->current()['_id']);
        $this->assertEquals('3rd Product', $pagination->current()['_source']['title']);
    }

    /**
     * @expectedException \InvalidArgumentException
     */
    public function testInvalidResultsType()
    {
        /** @var Repository $repo */
        $repo = $this->getIndexManager('bar')->getRepository('AcmeBarBundle:Product');
        $paginator = $this->getContainer()->get('knp_paginator');

        $query = ['query' => ['match_all' => []]];

        $adapter = $repo->find($query, Finder::ADAPTER_KNP);
        $paginator->paginate($adapter);
    }
}
