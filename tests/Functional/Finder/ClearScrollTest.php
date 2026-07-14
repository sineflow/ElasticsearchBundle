<?php

declare(strict_types=1);

namespace Sineflow\ElasticsearchBundle\Tests\Functional\Finder;

use Elastic\Elasticsearch\Exception\ClientResponseException;
use Jchook\AssertThrows\AssertThrows;
use Sineflow\ElasticsearchBundle\Finder\Finder;
use Sineflow\ElasticsearchBundle\Tests\AbstractElasticsearchTestCase;
use Sineflow\ElasticsearchBundle\Tests\App\Fixture\Acme\BarBundle\Document\Product;

/**
 * Reproduces how Elasticsearch responds when clearing a scroll context that the cluster has already released:
 * a 404 status with a body of {"succeeded":true,"num_freed":0}, which the client library raises as an exception
 * even though nothing actually went wrong.
 *
 * Whether the cluster releases a scroll context on its own (e.g. right after a scroll search that matched no documents)
 * depends on the ES version and shard state, so the regular scroll tests cannot reliably trigger this response.
 * Here the context is explicitly released first, which reproduces the 404 response deterministically on any ES version.
 */
final class ClearScrollTest extends AbstractElasticsearchTestCase
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
                    'title' => 'Baz Product',
                ],
            ],
        ];
    }

    public function testClearingAScrollContextAlreadyReleasedByTheClusterIsNotAnError(): void
    {
        $indexManager = $this->getIndexManager('bar');
        $client = $indexManager->getConnection()->getClient();

        // Open a real scroll context on the cluster, with a page size smaller than the number
        // of documents, so the context is guaranteed to remain open after the initial search
        $rawResults = $client->search([
            'index'  => $indexManager->getReadAlias(),
            'scroll' => '1m',
            'body'   => ['size' => 1, 'sort' => ['_doc']],
        ])->asArray();
        $scrollId = $rawResults['_scroll_id'];

        // Explicitly release the scroll context, so the cluster no longer holds one for this scroll id
        $clearResponse = $client->clearScroll(['body' => ['scroll_id' => $scrollId]])->asArray();
        $this->assertTrue($clearResponse['succeeded']);
        $this->assertGreaterThan(0, $clearResponse['num_freed'], 'The initial search must have left a scroll context open');

        // Clearing the same scroll id again is what the cluster sees when the Finder clears a
        // context the cluster has already released itself - it responds with the 404 reproduced
        // here, which the Finder must treat as success, as there is simply nothing left to free
        /** @var Finder $finder */
        $finder = $this->getContainer()->get(Finder::class);

        $this->assertNotThrows(ClientResponseException::class, static function () use ($finder, $scrollId): void {
            (new \ReflectionMethod(Finder::class, 'clearScroll'))->invoke($finder, [Product::class], $scrollId);
        });
    }
}
