<?php

namespace Sineflow\ElasticsearchBundle\Tests\Functional\Finder\Adapter;

use Knp\Bundle\PaginatorBundle\Pagination\SlidingPagination;
use Sineflow\ElasticsearchBundle\Document\Repository\Repository;
use Sineflow\ElasticsearchBundle\Finder\Adapter\KnpPaginatorAdapter;
use Sineflow\ElasticsearchBundle\Finder\Finder;
use Sineflow\ElasticsearchBundle\Tests\AbstractElasticsearchTestCase;
use Sineflow\ElasticsearchBundle\Tests\App\Fixture\Acme\BarBundle\Document\Product;
use Symfony\Component\HttpFoundation\Request;

class KnpPaginatorAdapterTest extends AbstractElasticsearchTestCase
{
    /**
     * {@inheritdoc}
     */
    protected function getDataArray(): array
    {
        return [
            'bar' => [
                [
                    '_id'      => '1',
                    'title'    => 'Foo Product',
                    'price'    => 10,
                    'category' => [
                        'title' => 'Bar',
                        'tags'  => [
                            ['tagname' => 'first tag'],
                            ['tagname' => 'second tag'],
                        ],
                    ],
                    'related_categories' => [
                        [
                            'title' => 'Acme',
                            'tags'  => [
                                ['tagname' => 'tutu'],
                            ],
                        ],
                        [
                            'title' => 'Doodle',
                        ],
                    ],
                    'ml_info-en' => 'info in English',
                    'ml_info-fr' => 'info in French',
                ],
                [
                    '_id'                => '2',
                    'title'              => 'Bar Product',
                    'price'              => 15,
                    'category'           => null,
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
                    '_id'                => '3',
                    'price'              => 5,
                    'title'              => '3rd Product',
                    'related_categories' => [],
                ],
                [
                    '_id' => '54321',
                ],
            ],
        ];
    }

    public function testPagination(): void
    {
        /** @var Repository $repo */
        $repo = $this->getIndexManager('bar')->getRepository();
        $paginator = $this->getContainer()->get('knp_paginator');

        $query = ['query' => ['match_all' => (object) []], 'sort' => [['_id' => ['order' => 'asc']]]];
        $query['aggs'] = ['avg_price' => ['avg' => ['field' => 'price']]];

        // Test object results

        /** @var KnpPaginatorAdapter $adapter */
        $adapter = $repo->find($query, Finder::RESULTS_OBJECT | Finder::ADAPTER_KNP);

        /** @var SlidingPagination $pagination */
        $pagination = $paginator->paginate($adapter, 2, 2);

        $this->assertCount(2, $pagination);
        $this->assertInstanceOf(Product::class, $pagination->offsetGet(0));
        $this->assertSame('3', $pagination->offsetGet(0)->id);
        $this->assertEqualsWithDelta(10.0, $pagination->getCustomParameter('aggregations')['avg_price']['value'], PHP_FLOAT_EPSILON);
        $this->assertIsArray($pagination->getCustomParameter('suggestions'));

        // Test array results

        /** @var KnpPaginatorAdapter $adapter */
        $adapter = $repo->find($query, Finder::RESULTS_ARRAY | Finder::ADAPTER_KNP);

        /** @var SlidingPagination $pagination */
        $pagination = $paginator->paginate($adapter, 2, 2);

        $this->assertSame(3, $pagination->key());
        $this->assertSame('3rd Product', $pagination->current()['title']);
        $this->assertNull($pagination->getCustomParameter('aggregations'));
        $this->assertNull($pagination->getCustomParameter('suggestions'));

        // Test raw results

        /** @var KnpPaginatorAdapter $adapter */
        $adapter = $repo->find($query, Finder::RESULTS_RAW | Finder::ADAPTER_KNP);

        /** @var SlidingPagination $pagination */
        $pagination = $paginator->paginate($adapter, 2, 2);

        $this->assertSame('3', $pagination->current()['_id']);
        $this->assertSame('3rd Product', $pagination->current()['_source']['title']);
        $this->assertEqualsWithDelta(10.0, $pagination->getCustomParameter('aggregations')['avg_price']['value'], PHP_FLOAT_EPSILON);
        $this->assertIsArray($pagination->getCustomParameter('suggestions'));
    }

    public function testPaginationSorting(): void
    {
        // Create an empty request to get around a bug in KNP paginator that assumes there is always a Request
        // https://github.com/KnpLabs/knp-components/issues/239
        $this->getContainer()->get('request_stack')->push(new Request());

        /** @var Repository $repo */
        $repo = $this->getIndexManager('bar')->getRepository();
        $paginator = $this->getContainer()->get('knp_paginator');

        $query = ['query' => ['match_all' => (object) []], 'sort' => [['price' => ['order' => 'asc']]]];

        /** @var KnpPaginatorAdapter $adapter */
        $adapter = $repo->find($query, Finder::RESULTS_OBJECT | Finder::ADAPTER_KNP);

        // Do not apply default order to KNP, so just use the one in the query
        /** @var SlidingPagination $pagination */
        $pagination = $paginator->paginate($adapter, 1, 3);
        $this->assertSame('3', $pagination->current()->id);

        // Test setting default order to KNP
        $pagination = $paginator->paginate($adapter, 1, 3, [
            'defaultSortFieldName' => '_id',
            'defaultSortDirection' => 'desc',
        ]);
        $this->assertSame('54321', $pagination->current()->id);
    }

    public function testInvalidResultsType(): void
    {
        /** @var Repository $repo */
        $repo = $this->getIndexManager('bar')->getRepository();
        $paginator = $this->getContainer()->get('knp_paginator');

        $query = ['query' => ['match_all' => (object) []]];

        $adapter = $repo->find($query, Finder::ADAPTER_KNP);

        $this->expectException(\InvalidArgumentException::class);
        $paginator->paginate($adapter);
    }
}
