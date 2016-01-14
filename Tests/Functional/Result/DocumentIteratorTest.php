<?php

namespace Sineflow\ElasticsearchBundle\Tests\Functional\Result;

use Sineflow\ElasticsearchBundle\Document\Repository\Repository;
use Sineflow\ElasticsearchBundle\Finder\Finder;
use Sineflow\ElasticsearchBundle\Result\DocumentIterator;
use Sineflow\ElasticsearchBundle\Tests\AbstractElasticsearchTestCase;

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
                'AcmeBarBundle:Product' => [
                    [
                        '_id' => '1',
                        'title' => 'Foo Product',
                        'category' => [
                            'title' => 'Bar',
                        ],
                        'related_categories' => [
                            [
                                'title' => 'Acme',
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

    /**
     * Iteration test.
     */
    public function testIteration()
    {
        /** @var Repository $repo */
        $repo = $this->getIndexManager('bar')->getRepository('AcmeBarBundle:Product');

        /** @var DocumentIterator $iterator */
        $iterator = $repo->find(['query' => ['match_all' => []], 'size' => 3, 'sort' => ['_id' => ['order' =>'asc']]], Finder::RESULTS_OBJECT);

        $this->assertInstanceOf('Sineflow\ElasticsearchBundle\Result\DocumentIterator', $iterator);

        $this->assertCount(3, $iterator);

        $this->assertEquals(4, $iterator->getTotalCount());

        $iteration = 0;
        foreach ($iterator as $document) {
            $categories = $document->relatedCategories;

            if ($iteration === 0) {
                $this->assertInstanceOf(
                    'Sineflow\ElasticsearchBundle\Tests\app\fixture\Acme\BarBundle\Document\ObjCategory',
                    $document->category
                );
            } else {
                $this->assertNull($document->category);
            }

            $this->assertInstanceOf(
                'Sineflow\ElasticsearchBundle\Tests\app\fixture\Acme\BarBundle\Document\Product',
                $document
            );
            $this->assertInstanceOf('Sineflow\ElasticsearchBundle\Result\ObjectIterator', $categories);

            foreach ($categories as $category) {
                $this->assertInstanceOf(
                    'Sineflow\ElasticsearchBundle\Tests\app\fixture\Acme\BarBundle\Document\ObjCategory',
                    $category
                );
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
        $repo = $this->getIndexManager('bar')->getRepository('AcmeBarBundle:Product');

        /** @var DocumentIterator $iterator */
        $iterator = $repo->find(['query' => ['match_all' => []], 'size' => 3, 'sort' => ['_id' => ['order' =>'asc']]], Finder::RESULTS_OBJECT);

        $i = 0;
        $expected = [
            'Foo Product',
            'Bar Product',
            '3rd Product'
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
        $repo = $this->getIndexManager('foo')->getRepository('AcmeFooBundle:Customer');
        /** @var DocumentIterator $iterator */
        $iterator = $repo->find(['query' => ['match_all' => []]], Finder::RESULTS_OBJECT);

        $this->assertNull($iterator->current());
    }

    /**
     * Test that aggregations are returned
     */
    public function testAggregations()
    {
        /** @var Repository $repo */
        $repo = $this->getIndexManager('bar')->getRepository('AcmeBarBundle:Product');

        /** @var DocumentIterator $iterator */
        $iterator = $repo->find([
            'query' => ['match_all' => []],
            'aggs' => [
                'my_count' => [
                    'value_count' => [
                        'field' => 'title'
                    ]
                ]
            ]
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
        $repo = $this->getIndexManager('bar')->getRepository('AcmeBarBundle:Product');

        /** @var DocumentIterator $iterator */
        $iterator = $repo->find([
            'query' => ['match_all' => []],
            'suggest' => [
                'title-suggestions' => [
                    'text' => ['prodcut foot'],
                    'term' => [
                        'size' => 3,
                        'field' => 'title'
                    ]
                ]
            ]
        ], Finder::RESULTS_OBJECT);

        $suggestions = $iterator->getSuggestions();
        $this->assertArrayHasKey('title-suggestions', $suggestions);
        $this->assertCount(2, $suggestions['title-suggestions']);
    }
}
