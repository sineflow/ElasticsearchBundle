<?php

namespace Sineflow\ElasticsearchBundle\Tests\Functional\Manager;

use Elasticsearch\Common\Exceptions\Missing404Exception;
use Sineflow\ElasticsearchBundle\Document\Provider\ElasticsearchProvider;
use Sineflow\ElasticsearchBundle\Document\Repository\Repository;
use Sineflow\ElasticsearchBundle\Exception\BulkRequestException;
use Sineflow\ElasticsearchBundle\Exception\Exception;
use Sineflow\ElasticsearchBundle\Finder\Finder;
use Sineflow\ElasticsearchBundle\Manager\ConnectionManager;
use Sineflow\ElasticsearchBundle\Manager\IndexManager;
use Sineflow\ElasticsearchBundle\Mapping\DocumentMetadata;
use Sineflow\ElasticsearchBundle\Tests\AbstractElasticsearchTestCase;
use Sineflow\ElasticsearchBundle\Tests\App\Fixture\Acme\BarBundle\Document\Product;
use Sineflow\ElasticsearchBundle\Tests\App\Fixture\Acme\BarBundle\Document\Repository\ProductRepository;
use Sineflow\ElasticsearchBundle\Tests\App\Fixture\Acme\FooBundle\Document\Customer;
use Sineflow\ElasticsearchBundle\Tests\App\Fixture\Acme\FooBundle\Document\Provider\CustomerProvider;

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
                [
                    '_id'      => 'doc1',
                    'title'    => 'Foo Product',
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
            'customer' => [
                [
                    '_id'    => 111,
                    'name'   => 'Jane Doe',
                    'active' => true,
                ],
            ],
            'backup' => [
                [
                    '_id'   => 'abcde',
                    'entry' => 'log entry',
                ],
            ],
        ];
    }

    public function testGetReadAliasAndGetWriteAlias()
    {
        $imWithAliases = $this->getIndexManager('customer', false);
        $this->assertEquals('sineflow-esb-test-customer', $imWithAliases->getReadAlias());
        $this->assertEquals('sineflow-esb-test-customer_write', $imWithAliases->getWriteAlias());

        $imWithoutAliases = $this->getIndexManager('bar', false);
        $this->assertEquals('sineflow-esb-test-bar', $imWithoutAliases->getReadAlias());
        $this->assertEquals('sineflow-esb-test-bar', $imWithoutAliases->getWriteAlias());
    }

    public function testCreateIndexWithAliases()
    {
        $imWithAliases = $this->getIndexManager('customer', false);
        $imWithAliases->createIndex();

        $this->assertTrue($imWithAliases->getConnection()->existsAlias(['name' => 'sineflow-esb-test-customer']), 'Read alias does not exist');
        $this->assertTrue($imWithAliases->getConnection()->existsAlias(['name' => 'sineflow-esb-test-customer_write']), 'Write alias does not exist');

        $indicesPointedByAliases = $imWithAliases->getConnection()->getClient()->indices()->getAlias(['name' => 'sineflow-esb-test-customer,sineflow-esb-test-customer_write']);
        $this->assertCount(1, $indicesPointedByAliases, 'Read and Write aliases must point to one and the same index');
    }

    public function testCreateIndexWithoutAliases()
    {
        $imWithoutAliases = $this->getIndexManager('bar', false);
        $imWithoutAliases->createIndex();

        $index = $imWithoutAliases->getConnection()->getClient()->indices()->getAlias(['index' => 'sineflow-esb-test-bar']);
        $this->assertCount(1, $index, 'Index was not created');
        $this->assertCount(0, \current($index)['aliases'], 'Index should not have any aliases pointing to it');
    }

    public function testDropIndexWithAliases()
    {
        $imWithAliases = $this->getIndexManager('customer', false);
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

        $this->expectException(Missing404Exception::class);
        $imWithAliases->getConnection()->getClient()->indices()->getAlias(['name' => 'sineflow-esb-test-customer,sineflow-esb-test-customer_write']);
    }

    public function testGetLiveIndexWhenNoIndexExists()
    {
        /** @var IndexManager $imWithAliases */
        $imWithAliases = $this->getIndexManager('customer', false);

        $this->expectException(Exception::class);
        $imWithAliases->getLiveIndex();
    }

    public function testGetLiveIndex()
    {
        /** @var IndexManager $imWithAliases */
        $imWithAliases = $this->getIndexManager('customer');
        $liveIndex = $imWithAliases->getLiveIndex();
        $this->assertMatchesRegularExpression('/^sineflow-esb-test-customer_[0-9_]+$/', $liveIndex);

        /** @var IndexManager $imWithoutAliases */
        $imWithoutAliases = $this->getIndexManager('bar');
        $liveIndex = $imWithoutAliases->getLiveIndex();
        $this->assertEquals('sineflow-esb-test-bar', $liveIndex);
    }

    public function testRebuildIndexWithoutAliases()
    {
        $imWithoutAliases = $this->getIndexManager('bar');
        $this->expectException(Exception::class);
        $imWithoutAliases->rebuildIndex();
    }

    public function testRebuildIndexWithoutDeletingOld()
    {
        $imWithAliases = $this->getIndexManager('customer');
        $liveIndex = $imWithAliases->getLiveIndex();

        $imWithAliases->rebuildIndex();

        $this->assertTrue($imWithAliases->getConnection()->getClient()->indices()->exists(['index' => $liveIndex]));
        $imWithAliases->getConnection()->getClient()->indices()->delete(['index' => $liveIndex]);

        $newLiveIndex = $imWithAliases->getLiveIndex();
        $this->assertNotEquals($liveIndex, $newLiveIndex);
    }

    public function testRebuildIndexAndDeleteOld()
    {
        $imWithAliases = $this->getIndexManager('customer');
        $liveIndex = $imWithAliases->getLiveIndex();

        $imWithAliases->rebuildIndex(true);

        $this->assertFalse($imWithAliases->getConnection()->getClient()->indices()->exists(['index' => $liveIndex]));

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

        $doc = $imWithoutAliases->getRepository()->getById(555);
        $this->assertNull($doc);

        $imWithoutAliases->getConnection()->commit();
        $doc = $imWithoutAliases->getRepository()->getById(555);
        $this->assertInstanceOf(Product::class, $doc);
        $this->assertEquals('Acme title', $doc->title);

        // Test persisting properties with null values
        $product->title = null;
        $imWithoutAliases->persist($product);
        $imWithoutAliases->getConnection()->commit();
        $doc = $imWithoutAliases->getRepository()->getById(555);
        $this->assertNull($doc->title, 'Null property value was not persisted');
    }

    public function testPersistForManagerWithAliasesWithoutAutocommit()
    {
        $imWithAliases = $this->getIndexManager('customer');
        $imWithAliases->getConnection()->setAutocommit(false);

        // Simulate state during rebuilding when write alias points to more than 1 index
        $settings = [
            'index' => 'sineflow-esb-test-temp',
            'body'  => ['mappings' => ['properties' => ['name' => ['type' => 'keyword']]]],
        ];
        $imWithAliases->getConnection()->getClient()->indices()->create($settings);
        $setAliasParams = [
            'body' => ['actions' => [['add' => ['index' => 'sineflow-esb-test-temp', 'alias' => $imWithAliases->getWriteAlias()]]]],
        ];
        $imWithAliases->getConnection()->getClient()->indices()->updateAliases($setAliasParams);

        $customer = new Customer();
        $customer->id = 555;
        $customer->name = 'John Doe';
        $imWithAliases->persist($customer);

        $doc = $imWithAliases->getRepository()->getById(555);
        $this->assertNull($doc);

        $imWithAliases->getConnection()->commit();

        $doc = $imWithAliases->getRepository()->getById(555);
        $this->assertInstanceOf(Customer::class, $doc);
        $this->assertEquals('John Doe', $doc->name);

        // Check that value is set in the additional index for the write alias as well
        $raw = $imWithAliases->getConnection()->getClient()->get([
            'index' => 'sineflow-esb-test-temp',
            'id'    => 555,
        ]);
        $this->assertEquals('John Doe', $raw['_source']['name']);

        $imWithAliases->getConnection()->getClient()->indices()->delete(['index' => 'sineflow-esb-test-temp']);
    }

    public function testPersistRawWithAutocommit()
    {
        $imWithAliases = $this->getIndexManager('customer');
        $imWithAliases->getConnection()->setAutocommit(true);

        $imWithAliases->persistRaw([
            '_id'    => 444,
            'name'   => 'Jane',
            '_score' => 1,
        ]);

        $doc = $imWithAliases->getRepository()->getById(444);
        $this->assertEquals('Jane', $doc->name);
    }

    public function testPersistStrictMappingDocRetrievedById()
    {
        $im = $this->getIndexManager('bar');
        $im->getConnection()->setAutocommit(true);
        $repo = $im->getRepository();
        /** @var Product $doc */
        $doc = $repo->getById('doc1');
        $doc->title = 'NewName';
        $im->persist($doc);

        $doc = $repo->getById('doc1');
        $this->assertEquals('NewName', $doc->title);
    }

    public function testUpdateWithCorrectParams()
    {
        $imWithAliases = $this->getIndexManager('customer');
        $imWithAliases->getConnection()->setAutocommit(true);

        $imWithAliases->update(111, [
            'name' => 'Alicia',
        ]);

        $doc = $imWithAliases->getRepository()->getById(111);
        $this->assertEquals('Alicia', $doc->name);
    }

    public function testUpdateInexistingDoc()
    {
        $imWithAliases = $this->getIndexManager('customer');
        $imWithAliases->getConnection()->setAutocommit(true);

        $this->expectException(BulkRequestException::class);
        $imWithAliases->update('non-existing-id', [
            'name' => 'Alicia',
        ]);
    }

    public function testDelete()
    {
        $imWithAliases = $this->getIndexManager('customer');
        $imWithAliases->getConnection()->setAutocommit(true);

        // Simulate state during rebuilding when write alias points to more than 1 index
        $settings = [
            'index' => 'sineflow-esb-test-temp',
            'body'  => ['mappings' => ['properties' => ['name' => ['type' => 'keyword']]]],
        ];
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
        $imWithAliases->delete('111');

        $doc = $imWithAliases->getRepository()->getById(111);
        $this->assertNull($doc);

        $this->expectException(Missing404Exception::class);
        // Check that value is deleted in the additional index for the write alias as well
        $imWithAliases->getConnection()->getClient()->get([
            'index' => 'sineflow-esb-test-temp',
            'id'    => 111,
        ]);
    }

    public function testReindexWithElasticsearchSelfProvider()
    {
        $im = $this->getIndexManager('backup');
        $im->getConnection()->setAutocommit(false);

        $rawDoc = $im->getRepository()->getById('abcde', Finder::RESULTS_RAW);
        $this->assertEquals(1, $rawDoc['_version']);

        $im->reindex('abcde');

        $rawDoc = $im->getRepository()->getById('abcde', Finder::RESULTS_RAW);
        $this->assertEquals(1, $rawDoc['_version']);

        $im->getConnection()->commit();

        $rawDoc = $im->getRepository()->getById('abcde', Finder::RESULTS_RAW);
        $this->assertEquals(2, $rawDoc['_version']);
        $this->assertEquals('log entry', $rawDoc['_source']['entry']);
    }

    public function testGetDataProvider()
    {
        $imWithAliases = $this->getIndexManager('customer', false);
        $dataProvider = $imWithAliases->getDataProvider();
        $this->assertInstanceOf(CustomerProvider::class, $dataProvider);
    }

    public function testGetDataProviderWhenNoCustomProviderIsSet()
    {
        $imWithAliases = $this->getIndexManager('bar', false);
        $dataProvider = $imWithAliases->getDataProvider();
        $this->assertInstanceOf(ElasticsearchProvider::class, $dataProvider);
    }

    public function testGetRepository()
    {
        $imWithoutAliases = $this->getIndexManager('bar', false);
        $this->assertInstanceOf(ProductRepository::class, $imWithoutAliases->getRepository());

        $imWithAliases = $this->getIndexManager('customer', false);
        $this->assertInstanceOf(Repository::class, $imWithAliases->getRepository());
    }

    public function testGetters()
    {
        $imWithAliases = $this->getIndexManager('customer', false);
        $imWithoutAliases = $this->getIndexManager('bar', false);

        $this->assertInstanceOf(ConnectionManager::class, $imWithAliases->getConnection());

        $this->assertTrue($imWithAliases->getUseAliases());
        $this->assertFalse($imWithoutAliases->getUseAliases());

        $this->assertEquals('customer', $imWithAliases->getManagerName());
        $this->assertEquals('bar', $imWithoutAliases->getManagerName());
    }

    public function testGetDocumentMetadata()
    {
        /** @var IndexManager $imWithAliases */
        $imWithAliases = $this->getIndexManager('customer', false);

        $indexMetadata = $imWithAliases->getDocumentMetadata();
        $this->assertInstanceOf(DocumentMetadata::class, $indexMetadata);
    }
}
