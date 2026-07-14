<?php

declare(strict_types=1);

namespace Sineflow\ElasticsearchBundle\Tests\Unit\Finder;

use Elastic\Elasticsearch\Exception\ClientResponseException;
use Elastic\Elasticsearch\Response\Elasticsearch;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Sineflow\ElasticsearchBundle\Client\Client;
use Sineflow\ElasticsearchBundle\Finder\Adapter\ScrollAdapter;
use Sineflow\ElasticsearchBundle\Finder\Finder;
use Sineflow\ElasticsearchBundle\Manager\ConnectionManager;
use Sineflow\ElasticsearchBundle\Manager\IndexManager;
use Sineflow\ElasticsearchBundle\Manager\IndexManagerRegistry;
use Sineflow\ElasticsearchBundle\Mapping\DocumentMetadataCollector;
use Sineflow\ElasticsearchBundle\Result\DocumentConverter;
use Sineflow\ElasticsearchBundle\Tests\App\Fixture\Acme\BarBundle\Document\Product;

/**
 * Unit test for the Finder's scroll context clearing.
 *
 * Elasticsearch responds to a clear-scroll request with a 404 ({"succeeded":true,"num_freed":0})
 * when the scroll context has already been released on the cluster, e.g. when the search matched no documents.
 * Whether that happens depends on the cluster state, so the functional tests cannot deterministically cover it.
 * This test pins the behaviour with a mocked client instead.
 */
final class FinderTest extends TestCase
{
    private Finder $finder;

    private Client&MockObject $client;

    protected function setUp(): void
    {
        $this->client = $this->createMock(Client::class);

        $connection = $this->createMock(ConnectionManager::class);
        $connection->method('getConnectionName')->willReturn('default');
        $connection->method('getClient')->willReturn($this->client);

        $indexManager = $this->createMock(IndexManager::class);
        $indexManager->method('getConnection')->willReturn($connection);
        $indexManager->method('getReadAlias')->willReturn('bar');

        $indexManagerRegistry = $this->createMock(IndexManagerRegistry::class);
        $indexManagerRegistry->method('get')->with('bar')->willReturn($indexManager);

        $documentMetadataCollector = $this->createMock(DocumentMetadataCollector::class);
        $documentMetadataCollector->method('getDocumentClassIndex')->with(Product::class)->willReturn('bar');

        $this->finder = new Finder(
            $documentMetadataCollector,
            $indexManagerRegistry,
            $this->createMock(DocumentConverter::class),
        );
    }

    public function testClearingAnAlreadyReleasedScrollContextIsNotAnError(): void
    {
        $this->stubEmptySearchResult();

        // Clearing a scroll context that the cluster has already released responds with
        // a 404, despite being successful: {"succeeded":true,"num_freed":0}
        $this->client->expects($this->once())
            ->method('clearScroll')
            ->willThrowException(new ClientResponseException('404 Not Found: {"succeeded":true,"num_freed":0}', 404));

        $scrollAdapter = $this->finder->find(
            [Product::class],
            ['query' => ['term' => ['title' => ['value' => 'nosuchtitle']]]],
            Finder::RESULTS_RAW | Finder::ADAPTER_SCROLL,
        );

        $this->assertInstanceOf(ScrollAdapter::class, $scrollAdapter);
    }

    public function testOtherClientErrorsWhenClearingAScrollContextAreStillRaised(): void
    {
        $this->stubEmptySearchResult();

        $this->client->expects($this->once())
            ->method('clearScroll')
            ->willThrowException(new ClientResponseException('403 Forbidden', 403));

        $this->expectException(ClientResponseException::class);
        $this->expectExceptionCode(403);

        $this->finder->find(
            [Product::class],
            ['query' => ['term' => ['title' => ['value' => 'nosuchtitle']]]],
            Finder::RESULTS_RAW | Finder::ADAPTER_SCROLL,
        );
    }

    /**
     * Stubs the initial scroll search request to match no documents,
     * which makes the Finder clear the scroll context right away.
     */
    private function stubEmptySearchResult(): void
    {
        $searchResponse = $this->createMock(Elasticsearch::class);
        $searchResponse->method('asArray')->willReturn([
            '_scroll_id' => 'DXF1ZXJ5QW5kRmV0Y2gBAAAAAAAAAAAWWm5vc3VjaHNjcm9sbGlk',
            'hits'       => [
                'total' => ['value' => 0, 'relation' => 'eq'],
                'hits'  => [],
            ],
        ]);

        $this->client->method('search')->willReturn($searchResponse);
    }
}
