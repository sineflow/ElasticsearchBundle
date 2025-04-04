<?php

namespace Sineflow\ElasticsearchBundle\Tests\Functional\Manager;

use Elastic\Elasticsearch\Exception\ClientResponseException;
use Elastic\Elasticsearch\Exception\ElasticsearchException;
use Elastic\Elasticsearch\Exception\MissingParameterException;
use Elastic\Elasticsearch\Exception\ServerResponseException;
use Psr\Cache\InvalidArgumentException;
use Sineflow\ElasticsearchBundle\Document\Provider\ElasticsearchProvider;
use Sineflow\ElasticsearchBundle\Document\Repository\Repository;
use Sineflow\ElasticsearchBundle\Exception\BulkRequestException;
use Sineflow\ElasticsearchBundle\Exception\IndexOrAliasNotFoundException;
use Sineflow\ElasticsearchBundle\Exception\IndexRebuildingWithoutAliasesException;
use Sineflow\ElasticsearchBundle\Finder\Finder;
use Sineflow\ElasticsearchBundle\Manager\ConnectionManager;
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
    protected function getDataArray(): array
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

    public function testGetReadAliasAndGetWriteAlias(): void
    {
        $imWithAliases = $this->getIndexManager('customer', false);
        $this->assertEquals('sineflow-esb-test-customer', $imWithAliases->getReadAlias());
        $this->assertEquals('sineflow-esb-test-customer_write', $imWithAliases->getWriteAlias());

        $imWithoutAliases = $this->getIndexManager('bar', false);
        $this->assertEquals('sineflow-esb-test-bar', $imWithoutAliases->getReadAlias());
        $this->assertEquals('sineflow-esb-test-bar', $imWithoutAliases->getWriteAlias());
    }

    /**
     * @throws ClientResponseException
     * @throws ServerResponseException
     */
    public function testCreateIndexWithAliases(): void
    {
        $imWithAliases = $this->getIndexManager('customer', false);
        $imWithAliases->createIndex();

        $this->assertTrue($imWithAliases->getConnection()->existsAlias(['name' => 'sineflow-esb-test-customer']), 'Read alias does not exist');
        $this->assertTrue($imWithAliases->getConnection()->existsAlias(['name' => 'sineflow-esb-test-customer_write']), 'Write alias does not exist');

        $indicesPointedByAliases = $imWithAliases->getConnection()->getClient()->indices()->getAlias(['name' => 'sineflow-esb-test-customer,sineflow-esb-test-customer_write'])->asArray();
        $this->assertCount(1, $indicesPointedByAliases, 'Read and Write aliases must point to one and the same index');
    }

    /**
     * @throws ClientResponseException
     * @throws ServerResponseException
     */
    public function testCreateIndexWithoutAliases(): void
    {
        $imWithoutAliases = $this->getIndexManager('bar', false);
        $imWithoutAliases->createIndex();

        $index = $imWithoutAliases->getConnection()->getClient()->indices()->getAlias(['index' => 'sineflow-esb-test-bar'])->asArray();
        $this->assertCount(1, $index, 'Index was not created');
        $this->assertCount(0, \current($index)['aliases'], 'Index should not have any aliases pointing to it');
    }

    /**
     * @throws ClientResponseException
     * @throws ServerResponseException
     * @throws MissingParameterException
     */
    public function testDropIndexWithAliases(): void
    {
        $imWithAliases = $this->getIndexManager('customer', false);
        $imWithAliases->createIndex();

        // Simulate state during rebuilding when write alias points to more than 1 index
        try {
            $imWithAliases->getConnection()->getClient()->indices()->delete(['index' => 'sineflow-esb-test-temp']);
        } catch (ElasticsearchException) {
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

        $this->expectException(ClientResponseException::class);
        $imWithAliases->getConnection()->getClient()->indices()->getAlias(['name' => 'sineflow-esb-test-customer,sineflow-esb-test-customer_write'])->asArray();
    }

    public function testGetLiveIndexWhenNoIndexExists(): void
    {
        $imWithAliases = $this->getIndexManager('customer', false);

        $this->expectException(IndexOrAliasNotFoundException::class);
        $imWithAliases->getLiveIndex();
    }

    public function testGetLiveIndex(): void
    {
        $imWithAliases = $this->getIndexManager('customer');
        $liveIndex = $imWithAliases->getLiveIndex();
        $this->assertMatchesRegularExpression('/^sineflow-esb-test-customer_[0-9_]+$/', $liveIndex);

        $imWithoutAliases = $this->getIndexManager('bar');
        $liveIndex = $imWithoutAliases->getLiveIndex();
        $this->assertEquals('sineflow-esb-test-bar', $liveIndex);
    }

    /**
     * @throws ClientResponseException
     * @throws ElasticsearchException
     * @throws MissingParameterException
     * @throws ServerResponseException
     * @throws IndexRebuildingWithoutAliasesException
     */
    public function testRebuildIndexWithoutAliases(): void
    {
        $imWithoutAliases = $this->getIndexManager('bar');
        $this->expectException(IndexRebuildingWithoutAliasesException::class);
        $imWithoutAliases->rebuildIndex();
    }

    /**
     * @throws ClientResponseException
     * @throws ElasticsearchException
     * @throws IndexRebuildingWithoutAliasesException
     * @throws MissingParameterException
     * @throws ServerResponseException
     */
    public function testRebuildIndexWithoutDeletingOld(): void
    {
        $imWithAliases = $this->getIndexManager('customer');
        $liveIndex = $imWithAliases->getLiveIndex();

        $imWithAliases->rebuildIndex();

        $this->assertTrue($imWithAliases->getConnection()->getClient()->indices()->exists(['index' => $liveIndex])->asBool());
        $imWithAliases->getConnection()->getClient()->indices()->delete(['index' => $liveIndex]);

        $newLiveIndex = $imWithAliases->getLiveIndex();
        $this->assertNotEquals($liveIndex, $newLiveIndex);
    }

    /**
     * @throws ClientResponseException
     * @throws ElasticsearchException
     * @throws IndexRebuildingWithoutAliasesException
     * @throws MissingParameterException
     * @throws ServerResponseException
     */
    public function testRebuildIndexAndDeleteOld(): void
    {
        $imWithAliases = $this->getIndexManager('customer');
        $liveIndex = $imWithAliases->getLiveIndex();

        $imWithAliases->rebuildIndex(true);

        $this->assertFalse($imWithAliases->getConnection()->getClient()->indices()->exists(['index' => $liveIndex])->asBool());

        $newLiveIndex = $imWithAliases->getLiveIndex();
        $this->assertNotEquals($liveIndex, $newLiveIndex);
    }

    /**
     * @throws InvalidArgumentException
     * @throws ClientResponseException
     * @throws ServerResponseException
     */
    public function testPersistForManagerWithoutAliasesWithoutAutocommit(): void
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

    /**
     * @throws ClientResponseException
     * @throws MissingParameterException
     * @throws InvalidArgumentException
     * @throws ServerResponseException
     */
    public function testPersistForManagerWithAliasesWithoutAutocommit(): void
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
        ])->asArray();
        $this->assertEquals('John Doe', $raw['_source']['name']);

        $imWithAliases->getConnection()->getClient()->indices()->delete(['index' => 'sineflow-esb-test-temp']);
    }

    /**
     * @throws ServerResponseException
     * @throws ClientResponseException
     * @throws InvalidArgumentException
     */
    public function testPersistRawWithAutocommit(): void
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

    /**
     * @throws ServerResponseException
     * @throws ClientResponseException
     * @throws InvalidArgumentException
     */
    public function testPersistStrictMappingDocRetrievedById(): void
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

    /**
     * @throws ServerResponseException
     * @throws ClientResponseException
     * @throws InvalidArgumentException
     */
    public function testUpdateWithCorrectParams(): void
    {
        $imWithAliases = $this->getIndexManager('customer');
        $imWithAliases->getConnection()->setAutocommit(true);

        $imWithAliases->update(111, [
            'name' => 'Alicia',
        ]);

        $doc = $imWithAliases->getRepository()->getById(111);
        $this->assertEquals('Alicia', $doc->name);
    }

    /**
     * @throws ServerResponseException
     * @throws ClientResponseException
     */
    public function testUpdateInexistingDoc(): void
    {
        $imWithAliases = $this->getIndexManager('customer');
        $imWithAliases->getConnection()->setAutocommit(true);

        $this->expectException(BulkRequestException::class);
        $imWithAliases->update('non-existing-id', [
            'name' => 'Alicia',
        ]);
    }

    /**
     * @throws InvalidArgumentException
     * @throws ClientResponseException
     * @throws ServerResponseException
     * @throws MissingParameterException
     */
    public function testDelete(): void
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

        $this->expectException(ClientResponseException::class);
        // Check that value is deleted in the additional index for the write alias as well
        $imWithAliases->getConnection()->getClient()->get([
            'index' => 'sineflow-esb-test-temp',
            'id'    => 111,
        ]);
    }

    /**
     * @throws ClientResponseException
     * @throws InvalidArgumentException
     * @throws ServerResponseException
     */
    public function testReindexWithElasticsearchSelfProvider(): void
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

    public function testGetDataProvider(): void
    {
        $imWithAliases = $this->getIndexManager('customer', false);
        $dataProvider = $imWithAliases->getDataProvider();
        $this->assertInstanceOf(CustomerProvider::class, $dataProvider);
    }

    public function testGetDataProviderWhenNoCustomProviderIsSet(): void
    {
        $imWithAliases = $this->getIndexManager('bar', false);
        $dataProvider = $imWithAliases->getDataProvider();
        $this->assertInstanceOf(ElasticsearchProvider::class, $dataProvider);
    }

    public function testGetRepository(): void
    {
        $imWithoutAliases = $this->getIndexManager('bar', false);
        $this->assertInstanceOf(ProductRepository::class, $imWithoutAliases->getRepository());

        $imWithAliases = $this->getIndexManager('customer', false);
        $this->assertInstanceOf(Repository::class, $imWithAliases->getRepository());
    }

    public function testGetters(): void
    {
        $imWithAliases = $this->getIndexManager('customer', false);
        $imWithoutAliases = $this->getIndexManager('bar', false);

        $this->assertInstanceOf(ConnectionManager::class, $imWithAliases->getConnection());

        $this->assertTrue($imWithAliases->getUseAliases());
        $this->assertFalse($imWithoutAliases->getUseAliases());

        $this->assertEquals('customer', $imWithAliases->getManagerName());
        $this->assertEquals('bar', $imWithoutAliases->getManagerName());
    }

    /**
     * @throws InvalidArgumentException
     */
    public function testGetDocumentMetadata(): void
    {
        $imWithAliases = $this->getIndexManager('customer', false);

        $indexMetadata = $imWithAliases->getDocumentMetadata();
        $this->assertInstanceOf(DocumentMetadata::class, $indexMetadata);
    }
}
