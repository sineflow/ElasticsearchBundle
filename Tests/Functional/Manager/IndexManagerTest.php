<?php

namespace Sineflow\ElasticsearchBundle\Tests\Functional\Manager;

use Elasticsearch\Common\Exceptions\Missing404Exception;
use Sineflow\ElasticsearchBundle\Document\Repository\Repository;
use Sineflow\ElasticsearchBundle\Exception\Exception;
use Sineflow\ElasticsearchBundle\Exception\IndexRebuildingException;
use Sineflow\ElasticsearchBundle\Finder\Finder;
use Sineflow\ElasticsearchBundle\Manager\ConnectionManager;
use Sineflow\ElasticsearchBundle\Manager\IndexManager;
use Sineflow\ElasticsearchBundle\Tests\AbstractElasticsearchTestCase;
use Sineflow\ElasticsearchBundle\Tests\app\fixture\Acme\BarBundle\Document\Product;
use Sineflow\ElasticsearchBundle\Tests\app\fixture\Acme\BarBundle\Document\Repository\ProductRepository;
use Sineflow\ElasticsearchBundle\Tests\app\fixture\Acme\FooBundle\Document\Customer;
use Sineflow\ElasticsearchBundle\Tests\app\fixture\Acme\FooBundle\Document\Provider\OrderProvider;

/**
 * Class IndexManagerTest
 */
class IndexManagerTest extends AbstractElasticsearchTestCase
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
                        '_id' => 'doc1',
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
                ],
            ],
            'foo' => [
                'AcmeFooBundle:Customer' => [
                    [
                        '_id' => 111,
                        'name' => 'Jane Doe',
                        'active' => true,
                    ],
                ],
            ],
        ];
    }

    public function testGetReadAliasAndGetWriteAlias()
    {
        $imWithAliases = $this->getIndexManager('foo', false);
        $this->assertEquals('sineflow-esb-test', $imWithAliases->getReadAlias());
        $this->assertEquals('sineflow-esb-test_write', $imWithAliases->getWriteAlias());

        $imWithoutAliases = $this->getIndexManager('bar', false);
        $this->assertEquals('sineflow-esb-test-bar', $imWithoutAliases->getReadAlias());
        $this->assertEquals('sineflow-esb-test-bar', $imWithoutAliases->getWriteAlias());
    }

    public function testCreateIndexWithAliases()
    {
        $imWithAliases = $this->getIndexManager('foo', false);
        $imWithAliases->createIndex();

        $this->assertTrue($imWithAliases->getConnection()->existsAlias(array('name' => 'sineflow-esb-test')), 'Read alias does not exist');
        $this->assertTrue($imWithAliases->getConnection()->existsAlias(array('name' => 'sineflow-esb-test_write')), 'Write alias does not exist');

        $indicesPointedByAliases = $imWithAliases->getConnection()->getClient()->indices()->getAlias(['name' => 'sineflow-esb-test,sineflow-esb-test_write']);
        $this->assertCount(1, $indicesPointedByAliases, 'Read and Write aliases must point to one and the same index');
    }

    public function testCreateIndexWithoutAliases()
    {
        $imWithoutAliases = $this->getIndexManager('bar', false);
        $imWithoutAliases->createIndex();

        $index = $imWithoutAliases->getConnection()->getClient()->indices()->getAliases(['index' => 'sineflow-esb-test-bar']);
        $this->assertCount(1, $index, 'Index was not created');
        $this->assertCount(0, current($index)['aliases'], 'Index should not have any aliases pointing to it');
    }

    public function testDropIndexWithAliases()
    {
        $imWithAliases = $this->getIndexManager('foo', false);
        $imWithAliases->createIndex();

        // Simulate state during rebuilding when write alias points to more than 1 index
        try {
            $imWithAliases->getConnection()->getClient()->indices()->delete(['index' => 'sineflow-esb-test-temp']);
        } catch (\Exception $e) {
        }
        $imWithAliases->getConnection()->getClient()->indices()->create(['index' => 'sineflow-esb-test-temp']);
        $setAliasParams = [
            'body' => [
                'actions' => [
                    [
                        'add' => [
                            'index' => 'sineflow-esb-test-temp',
                            'alias' => $imWithAliases->getWriteAlias(),
                        ],
                    ],
                ],
            ],
        ];
        $imWithAliases->getConnection()->getClient()->indices()->updateAliases($setAliasParams);

        $imWithAliases->dropIndex();

        $this->setExpectedException(Missing404Exception::class);
        $imWithAliases->getConnection()->getClient()->indices()->getAlias(['name' => 'sineflow-esb-test,sineflow-esb-test_write']);
    }

    /**
     * @expectedException Exception
     */
    public function testGetLiveIndexWhenNoIndexExists()
    {
        /** @var IndexManager $imWithAliases */
        $imWithAliases = $this->getIndexManager('foo', false);
        $imWithAliases->getLiveIndex();
    }

    public function testGetLiveIndex()
    {
        /** @var IndexManager $imWithAliases */
        $imWithAliases = $this->getIndexManager('foo');
        $liveIndex = $imWithAliases->getLiveIndex();
        $this->assertRegExp('/^sineflow-esb-test_[0-9_]+$/', $liveIndex);

        /** @var IndexManager $imWithoutAliases */
        $imWithoutAliases = $this->getIndexManager('bar');
        $liveIndex = $imWithoutAliases->getLiveIndex();
        $this->assertEquals('sineflow-esb-test-bar', $liveIndex);
    }

    /**
     * @expectedException Exception
     */
    public function testRebuildIndexWithoutAliases()
    {
        $imWithoutAliases = $this->getIndexManager('bar');
        $imWithoutAliases->rebuildIndex();
    }

    public function testRebuildIndexWithoutDeletingOld()
    {
        $imWithAliases = $this->getIndexManager('foo');
        $liveIndex = $imWithAliases->getLiveIndex();

        $imWithAliases->rebuildIndex();

        $this->assertTrue($imWithAliases->getConnection()->getClient()->indices()->exists(array('index' => $liveIndex)));
        $imWithAliases->getConnection()->getClient()->indices()->delete(['index' => $liveIndex]);

        $newLiveIndex = $imWithAliases->getLiveIndex();
        $this->assertNotEquals($liveIndex, $newLiveIndex);
    }

    public function testRebuildIndexAndDeleteOld()
    {
        $imWithAliases = $this->getIndexManager('foo');
        $liveIndex = $imWithAliases->getLiveIndex();

        $imWithAliases->rebuildIndex(true);

        $this->assertFalse($imWithAliases->getConnection()->getClient()->indices()->exists(array('index' => $liveIndex)));

        $newLiveIndex = $imWithAliases->getLiveIndex();
        $this->assertNotEquals($liveIndex, $newLiveIndex);
    }

    public function testPersistForManagerWithoutAliasesWithoutAutocommit()
    {
        $imWithoutAliases = $this->getIndexManager('bar');
        $imWithoutAliases->getConnection()->setAutocommit(false);

        $product = new Product();
        $product->id = 555;
        $product->title = 'Acme title';
        $imWithoutAliases->persist($product);

        $doc = $imWithoutAliases->getRepository('AcmeBarBundle:Product')->getById(555);
        $this->assertNull($doc);

        $imWithoutAliases->getConnection()->commit();
        $doc = $imWithoutAliases->getRepository('AcmeBarBundle:Product')->getById(555);
        $this->assertInstanceOf(Product::class, $doc);
        $this->assertEquals('Acme title', $doc->title);

        // Test persisting properties with null values
        $product->title = null;
        $imWithoutAliases->persist($product);
        $imWithoutAliases->getConnection()->commit();
        $doc = $imWithoutAliases->getRepository('AcmeBarBundle:Product')->getById(555);
        $this->assertEquals(null, $doc->title, 'Null property value was not persisted');
    }

    public function testPersistForManagerWithAliasesWithoutAutocommit()
    {
        $imWithAliases = $this->getIndexManager('foo');
        $imWithAliases->getConnection()->setAutocommit(false);

        // Simulate state during rebuilding when write alias points to more than 1 index
        $settings = array (
            'index' => 'sineflow-esb-test-temp',
            'body' => ['mappings' => ['customer' => ['properties' => ['name' => ['type' => 'string']]]]],
        );
        $imWithAliases->getConnection()->getClient()->indices()->create($settings);
        $setAliasParams = [
            'body' => ['actions' => [['add' => ['index' => 'sineflow-esb-test-temp', 'alias' => $imWithAliases->getWriteAlias()]]]],
        ];
        $imWithAliases->getConnection()->getClient()->indices()->updateAliases($setAliasParams);

        $customer = new Customer();
        $customer->id = 555;
        $customer->name = 'John Doe';
        $imWithAliases->persist($customer);

        $doc = $imWithAliases->getRepository('AcmeFooBundle:Customer')->getById(555);
        $this->assertNull($doc);

        $imWithAliases->getConnection()->commit();

        $doc = $imWithAliases->getRepository('AcmeFooBundle:Customer')->getById(555);
        $this->assertInstanceOf(Customer::class, $doc);
        $this->assertEquals('John Doe', $doc->name);

        // Check that value is set in the additional index for the write alias as well
        $raw = $imWithAliases->getConnection()->getClient()->get([
            'index' => 'sineflow-esb-test-temp',
            'type' => 'customer',
            'id' => 555,
        ]);
        $this->assertEquals('John Doe', $raw['_source']['name']);

        $imWithAliases->getConnection()->getClient()->indices()->delete(['index' => 'sineflow-esb-test-temp']);
    }

    public function testPersistRawWithAutocommit()
    {
        $imWithAliases = $this->getIndexManager('foo');
        $imWithAliases->getConnection()->setAutocommit(true);

        $imWithAliases->persistRaw('AcmeFooBundle:Customer', [
            '_id' => 444,
            'name' => 'Jane'
        ]);

        $doc = $imWithAliases->getRepository('AcmeFooBundle:Customer')->getById(444);
        $this->assertEquals('Jane', $doc->name);
    }

    /**
     * @expectedException InvalidArgumentException
     */
    public function testPersistRawWithInvalidDocumentClass()
    {
        $imWithAliases = $this->getIndexManager('foo');
        $imWithAliases->getConnection()->setAutocommit(true);

        $imWithAliases->persistRaw('AcmeFooBundle:NonExisting', [
            'bla' => 'blu'
        ]);
    }

    public function testUpdateWithCorrectParams()
    {
        $imWithAliases = $this->getIndexManager('foo');
        $imWithAliases->getConnection()->setAutocommit(true);

        $imWithAliases->update('AcmeFooBundle:Customer', 111, [
            'name' => 'Alicia'
        ]);

        $doc = $imWithAliases->getRepository('AcmeFooBundle:Customer')->getById(111);
        $this->assertEquals('Alicia', $doc->name);
    }

    /**
     * @expectedException \Sineflow\ElasticsearchBundle\Exception\BulkRequestException
     */
    public function testUpdateInexistingDoc()
    {
        $imWithAliases = $this->getIndexManager('foo');
        $imWithAliases->getConnection()->setAutocommit(true);

        $imWithAliases->update('AcmeFooBundle:Customer', 'non-existing-id', [
            'name' => 'Alicia'
        ]);
    }

    public function testDelete()
    {
        $imWithAliases = $this->getIndexManager('foo');
        $imWithAliases->getConnection()->setAutocommit(true);

        // Simulate state during rebuilding when write alias points to more than 1 index
        $settings = array (
            'index' => 'sineflow-esb-test-temp',
            'body' => ['mappings' => ['customer' => ['properties' => ['name' => ['type' => 'string']]]]],
        );
        $imWithAliases->getConnection()->getClient()->indices()->create($settings);
        $setAliasParams = [
            'body' => ['actions' => [['add' => ['index' => 'sineflow-esb-test-temp', 'alias' => $imWithAliases->getWriteAlias()]]]],
        ];
        $imWithAliases->getConnection()->getClient()->indices()->updateAliases($setAliasParams);

        $customer = new Customer();
        $customer->id = 111;
        $customer->name = 'John Doe';
        $imWithAliases->persist($customer);

        // Delete record in both physical indices pointed by alias
        $imWithAliases->delete('AcmeFooBundle:Customer', '111');

        $doc = $imWithAliases->getRepository('AcmeFooBundle:Customer')->getById(111);
        $this->assertNull($doc);

        $this->setExpectedException(Missing404Exception::class);
        // Check that value is deleted in the additional index for the write alias as well
        $imWithAliases->getConnection()->getClient()->get([
            'index' => 'sineflow-esb-test-temp',
            'type' => 'customer',
            'id' => 111,
        ]);
    }

    public function testReindexWithElasticsearchSelfProvider()
    {
        $imWithAliases = $this->getIndexManager('foo');
        $imWithAliases->getConnection()->setAutocommit(false);

        $rawDoc = $imWithAliases->getRepository('AcmeFooBundle:Customer')->getById(111, Finder::RESULTS_RAW);
        $this->assertEquals(1, $rawDoc['_version']);

        $imWithAliases->reindex('AcmeFooBundle:Customer', 111);

        $rawDoc = $imWithAliases->getRepository('AcmeFooBundle:Customer')->getById(111, Finder::RESULTS_RAW);
        $this->assertEquals(1, $rawDoc['_version']);

        $imWithAliases->getConnection()->commit();

        $rawDoc = $imWithAliases->getRepository('AcmeFooBundle:Customer')->getById(111, Finder::RESULTS_RAW);
        $this->assertEquals(2, $rawDoc['_version']);
        $this->assertEquals('Jane Doe', $rawDoc['_source']['name']);
    }

    public function testVerifyIndexAndAliasesState()
    {
        $imWithoutAliases = $this->getIndexManager('bar');
        $imWithoutAliases->verifyIndexAndAliasesState();

        $imWithAliases = $this->getIndexManager('foo');
        $imWithAliases->verifyIndexAndAliasesState();

        // Simulate state during rebuilding when write alias points to more than 1 index
        $settings = array (
            'index' => 'sineflow-esb-test-temp',
            'body' => ['mappings' => ['customer' => ['properties' => ['name' => ['type' => 'string']]]]],
        );
        $imWithAliases->getConnection()->getClient()->indices()->create($settings);
        $setAliasParams = [
            'body' => ['actions' => [['add' => ['index' => 'sineflow-esb-test-temp', 'alias' => $imWithAliases->getWriteAlias()]]]],
        ];
        $imWithAliases->getConnection()->getClient()->indices()->updateAliases($setAliasParams);

        $imWithAliases->verifyIndexAndAliasesState(false);

        $this->setExpectedException(IndexRebuildingException::class);
        $imWithAliases->verifyIndexAndAliasesState();
    }

    public function testGetDataProvider()
    {
        $imWithAliases = $this->getIndexManager('foo', false);
        $dataProvider = $imWithAliases->getDataProvider('AcmeFooBundle:Order');
        $this->assertInstanceOf(OrderProvider::class, $dataProvider);
    }

    public function testGetRepository()
    {
        $imWithoutAliases = $this->getIndexManager('bar', false);
        $this->assertInstanceOf(ProductRepository::class, $imWithoutAliases->getRepository('AcmeBarBundle:Product'));

        $imWithAliases = $this->getIndexManager('foo', false);
        $this->assertInstanceOf(Repository::class, $imWithAliases->getRepository('AcmeFooBundle:Customer'));
    }

    public function testGetters()
    {
        $imWithAliases = $this->getIndexManager('foo', false);
        $imWithoutAliases = $this->getIndexManager('bar', false);

        $this->assertInstanceOf(ConnectionManager::class, $imWithAliases->getConnection());

        $this->assertTrue($imWithAliases->getUseAliases());
        $this->assertFalse($imWithoutAliases->getUseAliases());

        $this->assertEquals('foo', $imWithAliases->getManagerName());
        $this->assertEquals('bar', $imWithoutAliases->getManagerName());
    }
}
