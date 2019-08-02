<?php

namespace Functional\Document;

use Jchook\AssertThrows\AssertThrows;
use Sineflow\ElasticsearchBundle\Document\Repository\Repository;
use Sineflow\ElasticsearchBundle\Finder\Finder;
use Sineflow\ElasticsearchBundle\Manager\IndexManager;
use Sineflow\ElasticsearchBundle\Mapping\DocumentMetadataCollector;
use Sineflow\ElasticsearchBundle\Tests\AbstractElasticsearchTestCase;
use Sineflow\ElasticsearchBundle\Tests\App\fixture\Acme\BarBundle\Document\Product;
use Sineflow\ElasticsearchBundle\Tests\App\fixture\Acme\FooBundle\Document\Customer;

class RepositoryTest extends AbstractElasticsearchTestCase
{
    use AssertThrows;

    /**
     * @var Repository
     */
    private $repository;

    /**
     * @var IndexManager
     */
    private $indexManager;

    /**
     * @var Finder
     */
    private $finder;

    /**
     * @var DocumentMetadataCollector
     */
    private $metadataCollector;

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
                    '_id' => 2,
                    'title' => 'ccc',
                ],
                [
                    '_id' => 3,
                    'title' => 'ccc',
                ],
            ],
        ];
    }

    protected function setUp()
    {
        parent::setUp();

        $this->finder            = $this->getContainer()->get('sfes.finder');
        $this->metadataCollector = $this->getContainer()->get('sfes.document_metadata_collector');
        $this->indexManager      = $this->getContainer()->get('sfes.index.bar');

        $this->indexManager->getConnection()->setAutocommit(true);

        $this->repository = new Repository($this->indexManager, Product::class, $this->finder, $this->metadataCollector);

        $this->getIndexManager('bar', !$this->hasCreatedIndexManager('bar'));
    }

    public function testConstructorWithEntityOfAnotherIndexManager()
    {
        $this->assertThrows(\InvalidArgumentException::class, function () {
            $this->repository = new Repository($this->indexManager, Customer::class, $this->finder, $this->metadataCollector);
        });
    }

    public function testGetIndexManager()
    {
        $this->assertInstanceOf(IndexManager::class, $this->repository->getIndexManager());
    }

    public function testGetById()
    {
        $doc = $this->repository->getById('doc1');
        $this->assertEquals('aaa', $doc->title);
    }

    public function testCount()
    {
        $searchBody = [
            'query' => [
                'match' => [
                    'title' => 'ccc',
                ],
            ],
        ];

        $this->assertEquals(2, $this->repository->count($searchBody));
    }

    public function testReindex()
    {
        $this->assertEquals(1, $this->repository->getById('doc1', Finder::RESULTS_RAW)['_version']);

        $this->indexManager->reindex('doc1');

        $rawDoc = $this->repository->getById('doc1', Finder::RESULTS_RAW);
        $this->assertEquals(2, $rawDoc['_version']);
        $this->assertEquals('aaa', $rawDoc['_source']['title']);
    }

    public function testDelete()
    {
        $this->repository->delete(3);

        $doc = $this->repository->getById(3);
        $this->assertNull($doc);
    }

    public function testUpdate()
    {
        $this->repository->update('doc1', ['title' => 'newTitle']);
        $this->assertEquals('newTitle', $this->repository->getById('doc1')->title);
    }

    public function testPersist()
    {
        $product = new Product();
        $product->id = 11;
        $product->title = 'Acme title';
        $this->indexManager->persist($product);

        $this->assertEquals('Acme title', $this->repository->getById(11)->title);
    }

    public function testPersistRaw()
    {
        $product = [
            '_id' => 22,
            'title' => 'Acme title'
        ];
        $this->indexManager->persistRaw($product);

        $this->assertEquals('Acme title', $this->repository->getById(22)->title);
    }
}
