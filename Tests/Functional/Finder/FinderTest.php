<?php

namespace Sineflow\ElasticsearchBundle\Tests\Functional\Finder;

use Sineflow\ElasticsearchBundle\Finder\Finder;
use Sineflow\ElasticsearchBundle\Finder\Adapter\KnpPaginatorAdapter;
use Sineflow\ElasticsearchBundle\Result\DocumentIterator;
use Sineflow\ElasticsearchBundle\Tests\AbstractElasticsearchTestCase;
use Sineflow\ElasticsearchBundle\Tests\App\fixture\Acme\BarBundle\Document\Product;
use Sineflow\ElasticsearchBundle\Tests\App\fixture\Acme\FooBundle\Document\Customer;

/**
 * Class FinderTest
 */
class FinderTest extends AbstractElasticsearchTestCase
{
    /**
     * {@inheritdoc}
     */
    protected function getDataArray()
    {
        return [
            'bar' => [
                [
                    '_id' => 'doc1',
                    'title' => 'aaa',
                ],
                [
                    '_id' => 'doc2',
                    'title' => 'bbb',
                ],
                [
                    '_id' => 3,
                    'title' => 'ccc',
                ],
            ],
            'customer' => [
                [
                    '_id' => 111,
                    'name' => 'Jane Doe',
                    'title' => 'aaa bbb',
                    'active' => true,
                ],
            ],
        ];
    }

    private $readOnlyIndexName = 'sineflow-customer-read-only-index-123';

    /**
     * {@inheritdoc}
     */
    protected function tearDown()
    {
        parent::tearDown();

        try {
            // Delete the read-only index we manually created
            $im = $this->getIndexManager('customer', false);
            $im->getConnection()->getClient()->indices()->delete(['index' => $this->readOnlyIndexName]);
        } catch (\Exception $e) {
            // Do nothing.
        }
    }

    /**
     * {@inheritdoc}
     */
    protected function setUp()
    {
        parent::setUp();

        // Create and populate indices just once for all tests in this class
        $this->getIndexManager('bar', !$this->hasCreatedIndexManager('bar'));
        $im = $this->getIndexManager('customer', !$this->hasCreatedIndexManager('customer'));

        // Create a second read-only index for 'customer' index manager, which uses aliases
        $settings = $im->getIndexMapping();
        $settings['index'] = $this->readOnlyIndexName;
        $im->getConnection()->getClient()->indices()->create($settings);
        $setAliasParams = [
            'body' => [
                'actions' => [
                    ['add' => ['index' => $this->readOnlyIndexName, 'alias' => $im->getReadAlias()]],
                ],
            ],
        ];
        $im->getConnection()->getClient()->indices()->updateAliases($setAliasParams);

        // Enter data in the read-only index
        $im->getConnection()->getClient()->index([
            'index' => $this->readOnlyIndexName,
            'id' => 111,
            'refresh' => true,
            'body' => [
                'name' => 'Another Jane',
            ],
        ]);
        $im->getConnection()->getClient()->index([
            'index' => $this->readOnlyIndexName,
            'id' => 123,
            'refresh' => true,
            'body' => [
                'name' => 'Jason Bourne',
            ],
        ]);
    }

    public function testGetById()
    {
        $finder = $this->getContainer()->get('sfes.finder');

        $docAsObject = $finder->get('AcmeBarBundle:Product', 'doc1');
        $this->assertInstanceOf(Product::class, $docAsObject);
        $this->assertEquals('aaa', $docAsObject->title);

        $docAsArray = $finder->get('AcmeBarBundle:Product', 'doc1', Finder::RESULTS_ARRAY);
        $this->assertEquals('aaa', $docAsArray['title']);

        $docAsRaw = $finder->get('AcmeBarBundle:Product', 'doc1', Finder::RESULTS_RAW);
        $this->assertArraySubset([
            '_index' => 'sineflow-esb-test-bar',
            '_id' => 'doc1',
            '_version' => 1,
            '_source' => ['title' => 'aaa'],
        ], $docAsRaw);

        $docAsObjectKNP = $finder->get('AcmeBarBundle:Product', 'doc1', Finder::RESULTS_OBJECT | Finder::ADAPTER_KNP);
        $this->assertInstanceOf(Product::class, $docAsObjectKNP);
    }

    public function testGetByIdWhenHavingAnotherReadOnlyIndex()
    {
        $finder = $this->getContainer()->get('sfes.finder');

        $doc = $finder->get(Customer::class, 111);

        // Make sure a document is returned for the duplicated id, whichever it is
        $this->assertInstanceOf(Customer::class, $doc);
    }

    public function testFindInMultipleTypesAndIndices()
    {
        $finder = $this->getContainer()->get('sfes.finder');

        $searchBody = [
            'query' => [
                'match' => [
                    'title' => 'bbb'
                ]
            ]
        ];

        $res = $finder->find(['AcmeBarBundle:Product', 'AcmeFooBundle:Customer'], $searchBody, Finder::RESULTS_OBJECT, [], $totalHits);
        $this->assertInstanceOf(DocumentIterator::class, $res);
        $this->assertEquals(2, count($res));
        $this->assertEquals(2, $totalHits);


        $res = $finder->find(['AcmeBarBundle:Product', 'AcmeFooBundle:Customer'], $searchBody, Finder::RESULTS_ARRAY);
        $this->assertArraySubset([
            'doc2' => [
                'title' => 'bbb',
            ],
            111 => [
                'name' => 'Jane Doe',
                'title' => 'aaa bbb',
                'active' => true,
            ]
        ], $res);


        $res = $finder->find(['AcmeBarBundle:Product', 'AcmeFooBundle:Customer'], $searchBody, Finder::RESULTS_RAW);
        $this->assertArrayHasKey('_shards', $res);
        $this->assertArrayHasKey('hits', $res);
        $this->assertArrayHasKey('total', $res['hits']);
        $this->assertArrayHasKey('max_score', $res['hits']);
        $this->assertArrayHasKey('hits', $res['hits']);
    }

    public function testFindForKNPPaginator()
    {
        $finder = $this->getContainer()->get('sfes.finder');

        $searchBody = [
            'query' => [
                'match' => [
                    'title' => 'bbb'
                ]
            ]
        ];

        $res = $finder->find(['AcmeBarBundle:Product'], $searchBody, Finder::RESULTS_OBJECT | Finder::ADAPTER_KNP);
        $this->assertInstanceOf(KnpPaginatorAdapter::class, $res);

        $res = $finder->find(['AcmeBarBundle:Product'], $searchBody, Finder::RESULTS_ARRAY | Finder::ADAPTER_KNP);
        $this->assertInstanceOf(KnpPaginatorAdapter::class, $res);

        $res = $finder->find(['AcmeBarBundle:Product'], $searchBody, Finder::RESULTS_RAW | Finder::ADAPTER_KNP);
        $this->assertInstanceOf(KnpPaginatorAdapter::class, $res);
    }

    public function testCount()
    {
        $finder = $this->getContainer()->get('sfes.finder');

        $searchBody = [
            'query' => [
                'match' => [
                    'title' => 'bbb'
                ]
            ]
        ];

        $this->assertEquals(1, $finder->count(['AcmeFooBundle:Customer'], $searchBody));
        $this->assertEquals(2, $finder->count(['AcmeBarBundle:Product', 'AcmeFooBundle:Customer'], $searchBody));
    }

    public function testGetTargetIndices()
    {
        $finder = $this->getContainer()->get('sfes.finder');

        $res = $finder->getTargetIndices(['AcmeBarBundle:Product', 'AcmeFooBundle:Customer']);

        $this->assertEquals([
            'sineflow-esb-test-bar',
            'sineflow-esb-test-customer',
        ], $res);
    }
}
