<?php

namespace Functional\Document;

use Jchook\AssertThrows\AssertThrows;
use Sineflow\ElasticsearchBundle\Document\Repository\Repository;
use Sineflow\ElasticsearchBundle\Finder\Finder;
use Sineflow\ElasticsearchBundle\Manager\IndexManager;
use Sineflow\ElasticsearchBundle\Mapping\DocumentMetadataCollector;
use Sineflow\ElasticsearchBundle\Tests\AbstractElasticsearchTestCase;

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
                    '_id'   => 'doc1',
                    'title' => 'aaa',
                ],
                [
                    '_id'   => 2,
                    'title' => 'ccc',
                ],
                [
                    '_id'   => 3,
                    'title' => 'ccc',
                ],
            ],
        ];
    }

    protected function setUp(): void
    {
        parent::setUp();

        $this->finder = $this->getContainer()->get(Finder::class);
        $this->indexManager = $this->getContainer()->get('sfes.index.bar');

        $this->indexManager->getConnection()->setAutocommit(true);

        $this->repository = new Repository($this->indexManager, $this->finder);

        $this->getIndexManager('bar', !$this->hasCreatedIndexManager('bar'));
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
}
