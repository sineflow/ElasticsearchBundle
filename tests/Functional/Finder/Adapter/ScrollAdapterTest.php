<?php

namespace Sineflow\ElasticsearchBundle\Tests\Functional\Finder\Adapter;

use Elastic\Elasticsearch\Exception\ClientResponseException;
use Jchook\AssertThrows\AssertThrows;
use PHPUnit\Framework\Attributes\Group;
use Sineflow\ElasticsearchBundle\Document\Repository\Repository;
use Sineflow\ElasticsearchBundle\Finder\Adapter\ScrollAdapter;
use Sineflow\ElasticsearchBundle\Finder\Finder;
use Sineflow\ElasticsearchBundle\Tests\AbstractElasticsearchTestCase;
use Sineflow\ElasticsearchBundle\Tests\App\Fixture\Acme\BarBundle\Document\Product;

class ScrollAdapterTest extends AbstractElasticsearchTestCase
{
    use AssertThrows;

    /**
     * {@inheritdoc}
     */
    protected function getDataArray(): array
    {
        return [
            'bar' => [
                [
                    '_id'   => '1',
                    'title' => 'Foo Product',
                ],
                [
                    '_id'   => '2',
                    'title' => 'Bar Product',
                ],
                [
                    '_id'   => '3',
                    'title' => '3rd product',
                ],
                [
                    '_id'   => 'aaa',
                    'title' => 'bla bla product',
                ],
                [
                    '_id'   => 'bbb',
                    'title' => 'blu blu',
                ],
                [
                    '_id' => '54321',
                ],
                [
                    '_id'   => '8',
                    'title' => 'product X',
                ],
                [
                    '_id'   => '9',
                    'title' => 'product Y',
                ],
            ],
        ];
    }

    public function testScanScroll(): void
    {
        /** @var Repository $repo */
        $repo = $this->getIndexManager('bar')->getRepository();

        $query = ['query' => ['term' => ['title' => ['value' => 'product']]]];

        // Test object results
        /** @var ScrollAdapter $scrollAdapter */
        $scrollAdapter = $repo->find($query, Finder::RESULTS_OBJECT | Finder::ADAPTER_SCROLL, ['size' => 2]);

        $this->assertInstanceOf(ScrollAdapter::class, $scrollAdapter);

        $i = 0;
        $scrolls = 0;
        while (false !== ($matches = $scrollAdapter->getNextScrollResults())) {
            foreach ($matches as $doc) {
                $this->assertInstanceOf(Product::class, $doc);
                ++$i;
            }
            ++$scrolls;
        }

        $this->assertSame(6, $i, 'Total matching documents iterated');
        $this->assertSame(6, $scrollAdapter->getTotalHits(), 'Total hits returned by scroll');
        $this->assertSame(3, $scrolls, 'Total number of scrolls');

        // Test array results
        /** @var ScrollAdapter $scrollAdapter */
        $scrollAdapter = $repo->find($query, Finder::RESULTS_ARRAY | Finder::ADAPTER_SCROLL, ['size' => 3, 'scroll' => '3m']);

        $this->assertInstanceOf(ScrollAdapter::class, $scrollAdapter);

        $i = 0;
        $scrolls = 0;
        $prevId = null;
        while (false !== ($matches = $scrollAdapter->getNextScrollResults())) {
            foreach ($matches as $id => $doc) {
                $this->assertIsArray($doc);
                $this->assertArrayHasKey('title', $doc, 'Document array returned');
                $this->assertNotEquals($prevId, $id, 'Document returned is the same as the previous one');
                ++$i;
                $prevId = $id;
            }
            ++$scrolls;
        }
        $this->assertSame(6, $i, 'Total matching documents iterated');
        $this->assertSame(6, $scrollAdapter->getTotalHits(), 'Total hits returned by scroll');
        $this->assertSame(2, $scrolls, 'Total number of scrolls');

        // Test raw results

        $query = ['query' => ['term' => ['title' => ['value' => 'product']]], 'sort' => ['_id']];

        /** @var ScrollAdapter $scrollAdapter */
        $scrollAdapter = $repo->find($query, Finder::RESULTS_RAW | Finder::ADAPTER_SCROLL, ['size' => 4]);

        $this->assertInstanceOf(ScrollAdapter::class, $scrollAdapter);

        $i = 0;
        $scrolls = 0;
        $prevId = null;
        while (false !== ($matches = $scrollAdapter->getNextScrollResults())) {
            $this->assertArrayHasKey('hits', $matches, 'Raw results returned');
            foreach ($matches['hits']['hits'] as $doc) {
                $this->assertIsArray($doc);
                $this->assertArrayHasKey('_id', $doc, 'Document array returned');
                $this->assertEquals($doc['_id'], $doc['sort'][0], 'The correct sort order is not applied');
                $this->assertNotEquals($prevId, $doc['_id'], 'Document returned is the same as the previous one');
                ++$i;
                $prevId = $doc['_id'];
            }
            ++$scrolls;
        }
        $this->assertSame(6, $i, 'Total matching documents iterated');
        $this->assertSame(6, $scrollAdapter->getTotalHits(), 'Total hits returned by scroll');
        $this->assertSame(2, $scrolls, 'Total number of scrolls');
    }

    public function testScrollContextIsClearedWhenSearchMatchesNoDocuments(): void
    {
        /** @var Repository $repo */
        $repo = $this->getIndexManager('bar')->getRepository();

        $openContextsBefore = $this->getOpenScrollContextsCount();

        /** @var ScrollAdapter $scrollAdapter */
        $scrollAdapter = $repo->find(
            ['query' => ['term' => ['title' => ['value' => 'nosuchtitle']]]],
            Finder::RESULTS_OBJECT | Finder::ADAPTER_SCROLL,
            ['size' => 2]
        );

        $this->assertSame($openContextsBefore, $this->getOpenScrollContextsCount(), 'The scroll context is cleared right away when the search matches no documents');
        $this->assertSame(0, $scrollAdapter->getTotalHits());

        $this->assertFalse($scrollAdapter->getNextScrollResults());
        $this->assertFalse($scrollAdapter->getNextScrollResults(), 'When the search matched no documents, repeated calls safely keep returning false, as no scroll request is ever made');
    }

    public function testScrollContextIsClearedWhenResultsAreExhausted(): void
    {
        /** @var Repository $repo */
        $repo = $this->getIndexManager('bar')->getRepository();

        $openContextsBefore = $this->getOpenScrollContextsCount();

        /** @var ScrollAdapter $scrollAdapter */
        $scrollAdapter = $repo->find(
            ['query' => ['term' => ['title' => ['value' => 'product']]]],
            Finder::RESULTS_OBJECT | Finder::ADAPTER_SCROLL,
            ['size' => 2]
        );

        while (false !== $scrollAdapter->getNextScrollResults()) {
        }

        $this->assertSame($openContextsBefore, $this->getOpenScrollContextsCount(), 'The scroll context is cleared once all results have been retrieved');

        // Once false has been returned, the scroll context is cleared and the method must not be called again -
        // doing so attempts a scroll request with a scroll id that no longer exists on the cluster
        $this->assertThrows(ClientResponseException::class, static function () use ($scrollAdapter): void {
            $scrollAdapter->getNextScrollResults();
        });
    }

    #[Group('AI')]
    public function testGetTotalHitsIsAvailableBeforeAnyResultsAreRetrieved(): void
    {
        /** @var Repository $repo */
        $repo = $this->getIndexManager('bar')->getRepository();

        /** @var ScrollAdapter $scrollAdapter */
        $scrollAdapter = $repo->find(
            ['query' => ['term' => ['title' => ['value' => 'product']]]],
            Finder::RESULTS_OBJECT | Finder::ADAPTER_SCROLL,
            ['size' => 2],
            $totalHits
        );

        $this->assertSame(6, $scrollAdapter->getTotalHits(), 'The total hits are available right away, before any results have been retrieved');
        $this->assertSame(6, $totalHits, 'The total hits are also returned through the out param of find()');
    }

    /**
     * Returns the total number of scroll contexts currently open on the cluster
     */
    private function getOpenScrollContextsCount(): int
    {
        $stats = $this->getIndexManager('bar', false)->getConnection()->getClient()->nodes()->stats([
            'metric'       => 'indices',
            'index_metric' => 'search',
        ])->asArray();

        return \array_sum(\array_map(static fn (array $node): int => $node['indices']['search']['open_contexts'], $stats['nodes']));
    }
}
