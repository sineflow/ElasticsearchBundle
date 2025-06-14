<?php

namespace Sineflow\ElasticsearchBundle\Tests\Functional\Finder;

use DMS\PHPUnitExtensions\ArraySubset\ArraySubsetAsserts;
use Sineflow\ElasticsearchBundle\Finder\Adapter\KnpPaginatorAdapter;
use Sineflow\ElasticsearchBundle\Finder\Finder;
use Sineflow\ElasticsearchBundle\Result\DocumentIterator;
use Sineflow\ElasticsearchBundle\Tests\AbstractElasticsearchTestCase;
use Sineflow\ElasticsearchBundle\Tests\App\Fixture\Acme\BarBundle\Document\Product;
use Sineflow\ElasticsearchBundle\Tests\App\Fixture\Acme\FooBundle\Document\Customer;
use Sineflow\ElasticsearchBundle\Tests\App\Fixture\Acme\FooBundle\Enum\CustomerTypeEnum;

/**
 * Class FinderTest
 */
class FinderTest extends AbstractElasticsearchTestCase
{
    use ArraySubsetAsserts;

    /**
     * {@inheritdoc}
     */
    protected function getDataArray(): array
    {
        return [
            'bar' => [
                [
                    '_id'   => 'doc1',
                    'title' => 'aaa',
                ],
                [
                    '_id'   => 'doc2',
                    'title' => 'bbb',
                ],
                [
                    '_id'   => 3,
                    'title' => 'ccc',
                ],
            ],
            'customer' => [
                [
                    '_id'           => 111,
                    'name'          => 'Jane Doe',
                    'title'         => 'aaa bbb',
                    'active'        => true,
                    'customer_type' => CustomerTypeEnum::COMPANY, // When php-elasticsearch serializes the request, json_encode will convert this to a scalar value
                ],
                [
                    '_id'           => 222,
                    'name'          => 'John Doe',
                    'title'         => 'bbb',
                    'customer_type' => 1,
                ],
            ],
        ];
    }

    private $readOnlyIndexName = 'sineflow-customer-read-only-index-123';

    /**
     * {@inheritdoc}
     */
    protected function tearDown(): void
    {
        parent::tearDown();

        try {
            // Delete the read-only index we manually created
            $im = $this->getIndexManager('customer', false);
            $im->getConnection()->getClient()->indices()->delete(['index' => $this->readOnlyIndexName]);
        } catch (\Exception) {
            // Do nothing.
        }
    }

    /**
     * {@inheritdoc}
     */
    protected function setUp(): void
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
            'index'   => $this->readOnlyIndexName,
            'id'      => 111,
            'refresh' => true,
            'body'    => [
                'name' => 'Another Jane',
            ],
        ]);
        $im->getConnection()->getClient()->index([
            'index'   => $this->readOnlyIndexName,
            'id'      => 123,
            'refresh' => true,
            'body'    => [
                'name' => 'Jason Bourne',
            ],
        ]);
    }

    public function testGetById(): void
    {
        /** @var Finder $finder */
        $finder = $this->getContainer()->get(Finder::class);

        $docAsObject = $finder->get(Product::class, 'doc1');
        $this->assertInstanceOf(Product::class, $docAsObject);
        $this->assertSame('aaa', $docAsObject->title);

        $docAsArray = $finder->get('AcmeBarBundle:Product', 'doc1', Finder::RESULTS_ARRAY);
        $this->assertSame('aaa', $docAsArray['title']);

        $docAsRaw = $finder->get('AcmeBarBundle:Product', 'doc1', Finder::RESULTS_RAW);
        $this->assertArraySubset([
            '_index'   => 'sineflow-esb-test-bar',
            '_id'      => 'doc1',
            '_version' => 1,
            '_source'  => ['title' => 'aaa'],
        ], $docAsRaw);

        $docAsObjectKNP = $finder->get('AcmeBarBundle:Product', 'doc1', Finder::RESULTS_OBJECT | Finder::ADAPTER_KNP);
        $this->assertInstanceOf(Product::class, $docAsObjectKNP);
    }

    public function testGetByIdWhenHavingAnotherReadOnlyIndex(): void
    {
        $finder = $this->getContainer()->get(Finder::class);

        $doc = $finder->get(Customer::class, 111);

        // Make sure a document is returned for the duplicated id, whichever it is
        $this->assertInstanceOf(Customer::class, $doc);
    }

    public function testFindInMultipleTypesAndIndices(): void
    {
        $finder = $this->getContainer()->get(Finder::class);

        $searchBody = [
            'query' => [
                'match' => [
                    'title' => 'bbb',
                ],
            ],
            'sort' => [
                '_id' => 'asc',
            ],
        ];

        $res = $finder->find(['AcmeBarBundle:Product', 'AcmeFooBundle:Customer'], $searchBody, Finder::RESULTS_OBJECT, [], $totalHits);
        $this->assertInstanceOf(DocumentIterator::class, $res);
        $this->assertCount(3, $res);
        $this->assertSame(3, $totalHits);
        $resAsArray = iterator_to_array($res);

        $this->assertInstanceOf(Customer::class, $resAsArray[0]);
        $this->assertInstanceOf(Customer::class, $resAsArray[1]);
        $this->assertInstanceOf(Product::class, $resAsArray[2]);

        $this->assertSame('111', $resAsArray[0]->id);
        $this->assertSame('Jane Doe', $resAsArray[0]->name);
        $this->assertSame(CustomerTypeEnum::COMPANY, $resAsArray[0]->customerType);

        $this->assertSame('222', $resAsArray[1]->id);
        $this->assertSame('John Doe', $resAsArray[1]->name);
        $this->assertSame(CustomerTypeEnum::INDIVIDUAL, $resAsArray[1]->customerType);

        $this->assertSame('doc2', $resAsArray[2]->id);
        $this->assertSame('bbb', $resAsArray[2]->title);

        $res = $finder->find(['AcmeBarBundle:Product', 'AcmeFooBundle:Customer'], $searchBody, Finder::RESULTS_ARRAY);
        $this->assertArraySubset([
            'doc2' => [
                'title' => 'bbb',
            ],
            111 => [
                'name'          => 'Jane Doe',
                'title'         => 'aaa bbb',
                'active'        => true,
                'customer_type' => 2,
            ],
            222 => [
                'name'          => 'John Doe',
                'title'         => 'bbb',
                'customer_type' => 1,
            ],
        ], $res);

        $res = $finder->find(['AcmeBarBundle:Product', 'AcmeFooBundle:Customer'], $searchBody, Finder::RESULTS_RAW);
        $this->assertArrayHasKey('_shards', $res);
        $this->assertArrayHasKey('hits', $res);
        $this->assertArrayHasKey('total', $res['hits']);
        $this->assertArrayHasKey('max_score', $res['hits']);
        $this->assertArrayHasKey('hits', $res['hits']);
    }

    public function testFindForKNPPaginator(): void
    {
        $finder = $this->getContainer()->get(Finder::class);

        $searchBody = [
            'query' => [
                'match' => [
                    'title' => 'bbb',
                ],
            ],
        ];

        $res = $finder->find(['AcmeBarBundle:Product'], $searchBody, Finder::RESULTS_OBJECT | Finder::ADAPTER_KNP);
        $this->assertInstanceOf(KnpPaginatorAdapter::class, $res);

        $res = $finder->find(['AcmeBarBundle:Product'], $searchBody, Finder::RESULTS_ARRAY | Finder::ADAPTER_KNP);
        $this->assertInstanceOf(KnpPaginatorAdapter::class, $res);

        $res = $finder->find(['AcmeBarBundle:Product'], $searchBody, Finder::RESULTS_RAW | Finder::ADAPTER_KNP);
        $this->assertInstanceOf(KnpPaginatorAdapter::class, $res);
    }

    public function testCount(): void
    {
        $finder = $this->getContainer()->get(Finder::class);

        $searchBody = [
            'query' => [
                'match' => [
                    'title' => 'bbb',
                ],
            ],
        ];

        $this->assertSame(2, $finder->count(['AcmeFooBundle:Customer'], $searchBody));
        $this->assertSame(3, $finder->count(['AcmeBarBundle:Product', 'AcmeFooBundle:Customer'], $searchBody));
    }

    public function testGetTargetIndices(): void
    {
        $finder = $this->getContainer()->get(Finder::class);

        $res = $finder->getTargetIndices(['AcmeBarBundle:Product', 'AcmeFooBundle:Customer']);

        $this->assertSame([
            'sineflow-esb-test-bar',
            'sineflow-esb-test-customer',
        ], $res);
    }
}
