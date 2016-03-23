<?php

namespace Sineflow\ElasticsearchBundle\Tests\Functional\Finder\Adapter;

use Knp\Bundle\PaginatorBundle\Pagination\SlidingPagination;
use Sineflow\ElasticsearchBundle\Document\Repository\Repository;
use Sineflow\ElasticsearchBundle\Finder\Adapter\ScanScrollAdapter;
use Sineflow\ElasticsearchBundle\Finder\Finder;
use Sineflow\ElasticsearchBundle\Tests\AbstractElasticsearchTestCase;
use Sineflow\ElasticsearchBundle\Tests\app\fixture\Acme\BarBundle\Document\Product;

class ScanScrollAdapterTest extends AbstractElasticsearchTestCase
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
                    ],
                    [
                        '_id' => '2',
                        'title' => 'Bar Product',
                    ],
                    [
                        '_id' => '3',
                        'title' => '3rd Product',
                    ],
                    [
                        '_id' => 'aaa',
                        'title' => 'bla bla product',
                    ],
                    [
                        '_id' => 'bbb',
                        'title' => 'blu blu',
                    ],
                    [
                        '_id' => '54321',
                    ],
                    [
                        '_id' => '8',
                        'title' => 'product X',
                    ],
                    [
                        '_id' => '9',
                        'title' => 'product Y',
                    ],
                ],
            ],
        ];
    }

    public function testScanScroll()
    {
        /** @var Repository $repo */
        $repo = $this->getIndexManager('bar')->getRepository('AcmeBarBundle:Product');

        $query = ['query' => ['term' => ['title' => ['value' => 'product']]]];

        // Test object results

        /** @var ScanScrollAdapter $scanScrollAdapter */
        $scanScrollAdapter = $repo->find($query, Finder::RESULTS_OBJECT | Finder::ADAPTER_SCANSCROLL, ['size' => 2]);

        $this->assertInstanceOf(ScanScrollAdapter::class, $scanScrollAdapter);

        $i = 0;
        $scrolls = 0;
        while (false !== ($matches = $scanScrollAdapter->getNextScrollResults())) {
            foreach ($matches as $doc) {
                $this->assertInstanceOf(Product::class, $doc);
                $i++;
            }
            $scrolls++;
        }
        $this->assertEquals(6, $i, 'Total matching documents iterated');
        $this->assertEquals(6, $scanScrollAdapter->getTotalHits(), 'Total hits returned by scroll');
        $this->assertEquals(3, $scrolls, 'Total number of scrolls');

        // Test array results

        /** @var ScanScrollAdapter $scanScrollAdapter */
        $scanScrollAdapter = $repo->find($query, Finder::RESULTS_ARRAY | Finder::ADAPTER_SCANSCROLL, ['size' => 2]);

        $this->assertInstanceOf(ScanScrollAdapter::class, $scanScrollAdapter);

        $i = 0;
        $scrolls = 0;
        $prevId = null;
        while (false !== ($matches = $scanScrollAdapter->getNextScrollResults())) {
            foreach ($matches as $id => $doc) {
                $this->assertInternalType('array', $doc);
                $this->assertArrayHasKey('title', $doc, 'Document array returned');
                $this->assertNotEquals($prevId, $id, 'Document return is not the same as the previous one');
                $i++;
                $prevId = $id;
            }
            $scrolls++;
        }
        $this->assertEquals(6, $i, 'Total matching documents iterated');
        $this->assertEquals(6, $scanScrollAdapter->getTotalHits(), 'Total hits returned by scroll');
        $this->assertEquals(3, $scrolls, 'Total number of scrolls');

        // Test raw results

        /** @var ScanScrollAdapter $scanScrollAdapter */
        $scanScrollAdapter = $repo->find($query, Finder::RESULTS_RAW | Finder::ADAPTER_SCANSCROLL, ['size' => 2]);

        $this->assertInstanceOf(ScanScrollAdapter::class, $scanScrollAdapter);

        $i = 0;
        $scrolls = 0;
        $prevId = null;
        while (false !== ($matches = $scanScrollAdapter->getNextScrollResults())) {
            $this->assertArrayHasKey('hits', $matches, 'Raw results returned');
            foreach ($matches['hits']['hits'] as $doc) {
                $this->assertInternalType('array', $doc);
                $this->assertArrayHasKey('_id', $doc, 'Document array returned');
                $this->assertNotEquals($prevId, $id, 'Document return is not the same as the previous one');
                $i++;
                $prevId = $doc['_id'];
            }
            $scrolls++;
        }
        $this->assertEquals(6, $i, 'Total matching documents iterated');
        $this->assertEquals(6, $scanScrollAdapter->getTotalHits(), 'Total hits returned by scroll');
        $this->assertEquals(3, $scrolls, 'Total number of scrolls');
    }
}
