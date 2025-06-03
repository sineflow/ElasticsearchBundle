<?php

namespace Functional\Document;

use Jchook\AssertThrows\AssertThrows;
use Sineflow\ElasticsearchBundle\Document\Repository\Repository;
use Sineflow\ElasticsearchBundle\Finder\Finder;
use Sineflow\ElasticsearchBundle\Manager\IndexManager;
use Sineflow\ElasticsearchBundle\Tests\AbstractElasticsearchTestCase;

class RepositoryTest extends AbstractElasticsearchTestCase
{
    use AssertThrows;

    private Repository $repository;

    private IndexManager $indexManager;

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

        $finder = $this->getContainer()->get(Finder::class);
        $this->indexManager = $this->getContainer()->get('sfes.index.bar');

        $this->indexManager->getConnection()->setAutocommit(true);

        $this->repository = new Repository($this->indexManager, $finder);

        $this->getIndexManager('bar', !$this->hasCreatedIndexManager('bar'));
    }

    public function testGetIndexManager(): void
    {
        $this->assertInstanceOf(IndexManager::class, $this->repository->getIndexManager());
    }

    public function testGetById(): void
    {
        $doc = $this->repository->getById('doc1');
        $this->assertSame('aaa', $doc->title);
        $doc = $this->repository->getById(2);
        $this->assertSame('ccc', $doc->title);
    }

    public function testCount(): void
    {
        $searchBody = [
            'query' => [
                'match' => [
                    'title' => 'ccc',
                ],
            ],
        ];

        $this->assertSame(2, $this->repository->count($searchBody));
    }

    public function testReindex(): void
    {
        $this->assertSame(1, $this->repository->getById('doc1', Finder::RESULTS_RAW)['_version']);

        $this->indexManager->reindex('doc1');

        $rawDoc = $this->repository->getById('doc1', Finder::RESULTS_RAW);
        $this->assertSame(2, $rawDoc['_version']);
        $this->assertSame('aaa', $rawDoc['_source']['title']);
    }
}
