<?php

namespace Sineflow\ElasticsearchBundle\Tests\Functional\Result;

use Sineflow\ElasticsearchBundle\Document\Repository\Repository;
use Sineflow\ElasticsearchBundle\Finder\Finder;
use Sineflow\ElasticsearchBundle\Result\DocumentIterator;
use Sineflow\ElasticsearchBundle\Result\ObjectIterator;
use Sineflow\ElasticsearchBundle\Tests\AbstractElasticsearchTestCase;
use Sineflow\ElasticsearchBundle\Tests\App\fixture\Acme\BarBundle\Document\ObjCategory;
use Sineflow\ElasticsearchBundle\Tests\App\fixture\Acme\BarBundle\Document\Product;

/**
 * Class DocumentIteratorTest
 */
class DocumentIteratorTest extends AbstractElasticsearchTestCase
{
    /**
     * {@inheritdoc}
     */
    protected function getDataArray()
    {
        return [
            'bar' => [
                [
                    '_id'                => '1',
                    'title'              => 'Foo Product',
                    'category'           => [
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
                    'ml_info-en'         => 'info in English',
                    'ml_info-fr'         => 'info in French',
                ],
                [
                    '_id'                => '2',
                    'title'              => 'Bar Product',
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
                    'title'              => '3rd Product',
                    'related_categories' => [],
                ],
                [
                    '_id' => '54321',
                ],
            ],
        ];
    }

    /**
     * Iteration test.
     */
    public function testIteration()
    {
        /** @var Repository $repo */
        $repo = $this->getIndexManager('bar')->getRepository();

        /** @var DocumentIterator $iterator */
        $iterator = $repo->find(
            ['query' => ['match_all' => (object)[]], 'size' => 3, 'sort' => ['_id' => ['order' => 'asc']]],
            Finder::RESULTS_OBJECT
        );

        $this->assertInstanceOf(DocumentIterator::class, $iterator);

        $this->assertCount(3, $iterator);

        $this->assertEquals(4, $iterator->getTotalCount());

        $iteration = 0;
        /** @var Product $document */
        foreach ($iterator as $document) {
            $categories = $document->relatedCategories;

            if ($iteration === 0) {
                $this->assertInstanceOf(ObjCategory::class, $document->category);
            } else {
                $this->assertNull($document->category);
            }

            $this->assertInstanceOf(Product::class, $document);
            $this->assertInstanceOf(ObjectIterator::class, $categories);

            foreach ($categories as $category) {
                $this->assertInstanceOf(ObjCategory::class, $category);
            }

            $iteration++;
        }
    }

    /**
     * Manual iteration test.
     */
    public function testManualIteration()
    {
        /** @var Repository $repo */
        $repo = $this->getIndexManager('bar')->getRepository();

        /** @var DocumentIterator $iterator */
        $iterator = $repo->find(
            ['query' => ['match_all' => (object) []], 'size' => 3, 'sort' => ['_id' => ['order' => 'asc']]],
            Finder::RESULTS_OBJECT
        );

        $i = 0;
        $expected = [
            'Foo Product',
            'Bar Product',
            '3rd Product',
        ];
        while ($iterator->valid()) {
            $this->assertEquals($i, $iterator->key());
            $this->assertEquals($expected[$i], $iterator->current()->title);
            $iterator->next();
            $i++;
        }
        $iterator->rewind();
        $this->assertEquals($expected[0], $iterator->current()->title);
    }

    /**
     * Tests if current() returns null when data doesn't exist.
     */
    public function testCurrentWithEmptyIterator()
    {
        /** @var Repository $repo */
        $repo = $this->getIndexManager('customer')->getRepository();
        /** @var DocumentIterator $iterator */
        $iterator = $repo->find(['query' => ['match_all' => (object) []]], Finder::RESULTS_OBJECT);

        $this->assertNull($iterator->current());
    }

    /**
     * Make sure null is returned when field doesn't exist or is empty and ObjectIterator otherwise
     */
    public function testNestedObjectIterator()
    {
        /** @var Repository $repo */
        $repo = $this->getIndexManager('bar')->getRepository();
        /** @var DocumentIterator $iterator */
        $products = $repo->find(['query' => ['match_all' => (object) []]], Finder::RESULTS_OBJECT);

        $this->assertCount(4, $products);
        /** @var Product $product */
        foreach ($products as $product) {
            $this->assertContains($product->id, ['1', '2', '3', '54321']);
            switch ($product->id) {
                case '54321':
                    $this->assertNull($product->relatedCategories);
                    break;
                case '3':
                    $this->assertInstanceOf(ObjectIterator::class, $product->relatedCategories);
                    $this->assertNull($product->relatedCategories->current());
                    break;
                default:
                    $this->assertInstanceOf(ObjectIterator::class, $product->relatedCategories);
                    $this->assertCount(2, $product->relatedCategories);
                    $this->assertInstanceOf(ObjCategory::class, $product->relatedCategories->current());
            }
        }
    }

    /**
     * Test that aggregations are returned
     */
    public function testAggregations()
    {
        /** @var Repository $repo */
        $repo = $this->getIndexManager('bar')->getRepository();

        /** @var DocumentIterator $iterator */
        $iterator = $repo->find([
            'query' => ['match_all' => (object) []],
            'aggs' => [
                'my_count' => [
                    'value_count' => [
                        'field' => 'title.raw',
                    ],
                ],
            ],
        ], Finder::RESULTS_OBJECT);

        $aggregations = $iterator->getAggregations();
        $this->assertArrayHasKey('my_count', $aggregations);
        $this->assertCount(1, $aggregations['my_count']);
    }

    /**
     * Test that suggestions are returned
     */
    public function testSuggestions()
    {
        /** @var Repository $repo */
        $repo = $this->getIndexManager('bar')->getRepository();

        /** @var DocumentIterator $iterator */
        $iterator = $repo->find([
            'query' => ['match_all' => (object) []],
            'suggest' => [
                'title-suggestions' => [
                    'text' => ['prodcut foot'],
                    'term' => [
                        'size' => 3,
                        'field' => 'title',
                    ],
                ],
            ],
        ], Finder::RESULTS_OBJECT);

        $suggestions = $iterator->getSuggestions();
        $this->assertArrayHasKey('title-suggestions', $suggestions);
        $this->assertCount(2, $suggestions['title-suggestions']);
    }
}
