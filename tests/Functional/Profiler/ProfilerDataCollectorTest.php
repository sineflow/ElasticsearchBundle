<?php

namespace Sineflow\ElasticsearchBundle\Tests\Functional\Profiler;

use DMS\PHPUnitExtensions\ArraySubset\ArraySubsetAsserts;
use Sineflow\ElasticsearchBundle\Profiler\ProfilerDataCollector;
use Sineflow\ElasticsearchBundle\Tests\AbstractElasticsearchTestCase;
use Sineflow\ElasticsearchBundle\Tests\App\Fixture\Acme\BarBundle\Document\Product;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

class ProfilerDataCollectorTest extends AbstractElasticsearchTestCase
{
    use ArraySubsetAsserts;

    /**
     * {@inheritdoc}
     */
    protected function getDataArray(): array
    {
        return [
            'default' => [
                'product' => [
                    [
                        '_id'   => 1,
                        'title' => 'foo',
                    ],
                    [
                        '_id'   => 2,
                        'title' => 'bar',
                    ],
                    [
                        '_id'   => 3,
                        'title' => 'pizza',
                    ],
                ],
            ],
        ];
    }

    /**
     * Tests if right amount of queries are caught
     */
    public function testGetQueryCount(): void
    {
        $imWithoutAliases = $this->getIndexManager('bar');
        $imWithoutAliases->getConnection()->setAutocommit(false);

        // Setting up the test takes 3 queries:
        // 1. DELETE query to remove existing index,
        // 2. Internal call to GET /_aliases to check if index/alias already exists
        // 3. PUT request to create index
        $this->assertSame(3, $this->getCollector()->getQueryCount());

        $product = new Product();
        $product->title = 'tuna';
        $imWithoutAliases->persist($product);
        $imWithoutAliases->getConnection()->commit();

        // It takes 3 more queries to insert the new document:
        // 1. GET /sineflow-esb-test-bar/_alias to check if more than one index should be written to
        // 2. POST bulk request to enter the data
        // 3. GET /_refresh, because of the $forceRefresh param of ->commit()
        $this->assertSame(6, $this->getCollector()->getQueryCount());
    }

    /**
     * Tests if correct time is being returned.
     */
    public function testGetTime(): void
    {
        $imWithoutAliases = $this->getIndexManager('bar');
        $imWithoutAliases->getConnection()->setAutocommit(true);
        $imWithoutAliases->getRepository()->getById(3);

        $this->assertGreaterThan(0.0, $this->getCollector()->getTotalQueryTime(), 'Time should be greater than 0ms');
        $this->assertLessThan(2000.0, $this->getCollector()->getTotalQueryTime(), 'Time should be less than 2s');
    }

    /**
     * Tests if logged data seems correct
     */
    public function testCorrectDataLogged(): void
    {
        $imWithoutAliases = $this->getIndexManager('bar');
        $imWithoutAliases->getConnection()->setAutocommit(true);
        $imWithoutAliases->getRepository()->getById(3);

        $queries = $this->getCollector()->getQueries();

        $lastQuery = \end($queries);
        $this->checkQueryParameters($lastQuery);

        $esHostAndPort = \explode(':', (string) $imWithoutAliases->getConnection()->getConnectionSettings()['hosts'][0]);

        $this->assertArraySubset(
            [
                'curlRequest'   => "curl -XPOST 'http://".\implode(':', $esHostAndPort)."/sineflow-esb-test-bar/_search?pretty=true' -d '{\"query\":{\"ids\":{\"values\":[3]}},\"version\":true}'",
                'kibanaRequest' => "POST /sineflow-esb-test-bar/_search\n{\"query\":{\"ids\":{\"values\":[3]}},\"version\":true}",
                'method'        => 'POST',
                'scheme'        => 'http',
                'host'          => $esHostAndPort[0],
                'port'          => (int) $esHostAndPort[1],
                'path'          => '/sineflow-esb-test-bar/_search',
            ],
            $lastQuery,
            'Logged data did not match expected data.'
        );
    }

    /**
     * Checks query parameters that are not static.
     */
    public function checkQueryParameters(array $query): void
    {
        $this->assertArrayHasKey('queryDuration', $query, 'Query should have queryDuration set.');
        $this->assertGreaterThan(0.0, $query['queryDuration'], 'Query duration should be greater than 0');

        $this->assertArrayHasKey('curlRequest', $query, 'Query should have curlRequest set.');
        $this->assertNotEmpty($query['curlRequest'], 'curlRequest should not be empty');

        $this->assertArrayHasKey('kibanaRequest', $query, 'Query should have kibanaRequest set.');
        $this->assertNotEmpty($query['kibanaRequest'], 'kibanaRequest should not be empty.');

        $this->assertArrayHasKey('backtrace', $query, 'Query should have backtrace set.');

        $this->assertArrayHasKey('scheme', $query, 'Query should have scheme set.');
        $this->assertNotEmpty($query['scheme'], 'scheme should not be empty.');

        $this->assertArrayHasKey('host', $query, 'Query should have host set.');
        $this->assertNotEmpty($query['host'], 'Host should not be empty');

        $this->assertArrayHasKey('port', $query, 'Query should have port set.');
        $this->assertNotEmpty($query['port'], 'port should not be empty.');

        $this->assertArrayHasKey('path', $query, 'Query should have host path set.');
        $this->assertNotEmpty($query['path'], 'Path should not be empty.');
    }

    private function getCollector(): ProfilerDataCollector
    {
        /** @var ProfilerDataCollector $collector */
        $collector = $this->getContainer()->get(ProfilerDataCollector::class);
        $collector->collect(new Request(), new Response());

        return $collector;
    }
}
