<?php

namespace Sineflow\ElasticsearchBundle\Tests\Functional\Finder\Adapter;

use Sineflow\ElasticsearchBundle\Document\Repository\Repository;
use Sineflow\ElasticsearchBundle\Finder\Adapter\ScrollAdapter;
use Sineflow\ElasticsearchBundle\Finder\Finder;
use Sineflow\ElasticsearchBundle\Tests\AbstractElasticsearchTestCase;
use Sineflow\ElasticsearchBundle\Tests\App\fixture\Acme\BarBundle\Document\Product;

class ScrollAdapterTest extends AbstractElasticsearchTestCase
{
    /**
     * {@inheritdoc}
     */
    protected function getDataArray()
    {
        return [
            'bar' => [
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
                    'title' => '3rd product',
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
        ];
    }

    public function testScanScroll()
    {
        /** @var Repository $repo */
        $repo = $this->getIndexManager('bar')->getRepository();

        $query = ['query' => ['term' => ['title' => ['value' => 'product']]]];

        // Test object results
        /** @var ScrollAdapter $scrollAdapter */
        $scrollAdapter = $repo->find($query, Finder::RESULTS_OBJECT | Finder::ADAPTER_SCROLL, ['size' => 2]);

        $this->assertInstanceOf(ScrollAdapter::class, $scrollAdapter);

        $i = 0;
        $scrolls = 0;
        while (false !== ($matches = $scrollAdapter->getNextScrollResults())) {
            foreach ($matches as $doc) {
                $this->assertInstanceOf(Product::class, $doc);
                $i++;
            }
            $scrolls++;
        }

        $this->assertEquals(6, $i, 'Total matching documents iterated');
        $this->assertEquals(6, $scrollAdapter->getTotalHits(), 'Total hits returned by scroll');
        $this->assertEquals(3, $scrolls, 'Total number of scrolls');


        // Test array results
        /** @var ScrollAdapter $scrollAdapter */
        $scrollAdapter = $repo->find($query, Finder::RESULTS_ARRAY | Finder::ADAPTER_SCROLL, ['size' => 3, 'scroll' => '3m']);

        $this->assertInstanceOf(ScrollAdapter::class, $scrollAdapter);

        $i = 0;
        $scrolls = 0;
        $prevId = null;
        while (false !== ($matches = $scrollAdapter->getNextScrollResults())) {
            foreach ($matches as $id => $doc) {
                $this->assertInternalType('array', $doc);
                $this->assertArrayHasKey('title', $doc, 'Document array returned');
                $this->assertNotEquals($prevId, $id, 'Document returned is the same as the previous one');
                $i++;
                $prevId = $id;
            }
            $scrolls++;
        }
        $this->assertEquals(6, $i, 'Total matching documents iterated');
        $this->assertEquals(6, $scrollAdapter->getTotalHits(), 'Total hits returned by scroll');
        $this->assertEquals(2, $scrolls, 'Total number of scrolls');

        // Test raw results

        $query = ['query' => ['term' => ['title' => ['value' => 'product']]], 'sort' => ['_id']];

        /** @var ScrollAdapter $scrollAdapter */
        $scrollAdapter = $repo->find($query, Finder::RESULTS_RAW | Finder::ADAPTER_SCROLL, ['size' => 4]);

        $this->assertInstanceOf(ScrollAdapter::class, $scrollAdapter);

        $i = 0;
        $scrolls = 0;
        $prevId = null;
        while (false !== ($matches = $scrollAdapter->getNextScrollResults())) {
            $this->assertArrayHasKey('hits', $matches, 'Raw results returned');
            foreach ($matches['hits']['hits'] as $doc) {
                $this->assertInternalType('array', $doc);
                $this->assertArrayHasKey('_id', $doc, 'Document array returned');
                $this->assertEquals($doc['_id'], $doc['sort'][0], 'The correct sort order is not applied');
                $this->assertNotEquals($prevId, $doc['_id'], 'Document returned is the same as the previous one');
                $i++;
                $prevId = $doc['_id'];
            }
            $scrolls++;
        }
        $this->assertEquals(6, $i, 'Total matching documents iterated');
        $this->assertEquals(6, $scrollAdapter->getTotalHits(), 'Total hits returned by scroll');
        $this->assertEquals(2, $scrolls, 'Total number of scrolls');
    }
}
