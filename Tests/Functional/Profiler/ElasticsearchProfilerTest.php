<?php

namespace Sineflow\ElasticsearchBundle\Tests\Functional\Profiler;

use DMS\PHPUnitExtensions\ArraySubset\ArraySubsetAsserts;
use Sineflow\ElasticsearchBundle\Profiler\ElasticsearchProfiler;
use Sineflow\ElasticsearchBundle\Tests\AbstractElasticsearchTestCase;
use Sineflow\ElasticsearchBundle\Tests\App\fixture\Acme\BarBundle\Document\Product;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

class ElasticsearchProfilerTest extends AbstractElasticsearchTestCase
{
    use ArraySubsetAsserts;

    /**
     * {@inheritdoc}
     */
    protected function getDataArray()
    {
        return [
            'default' => [
                'product' => [
                    [
                        '_id' => 1,
                        'title' => 'foo',
                    ],
                    [
                        '_id' => 2,
                        'title' => 'bar',
                    ],
                    [
                        '_id' => 3,
                        'title' => 'pizza',
                    ],
                ],
            ],
        ];
    }

    /**
     * Tests if right amount of queries are caught
     */
    public function testGetQueryCount()
    {
        $imWithoutAliases = $this->getIndexManager('bar');
        $imWithoutAliases->getConnection()->setAutocommit(false);

        // Setting up the test takes 3 queries:
        // 1. DELETE query to remove existing index,
        // 2. Internal call to GET /_aliases to check if index/alias already exists
        // 3. PUT request to create index
        $this->assertEquals(3, $this->getCollector()->getQueryCount());

        $product = new Product();
        $product->title = 'tuna';
        $imWithoutAliases->persist($product);
        $imWithoutAliases->getConnection()->commit();

        // It takes 3 more queries to insert the new document:
        // 1. GET /sineflow-esb-test-bar/_alias to check if more than one index should be written to
        // 2. POST bulk request to enter the data
        // 3. GET /_refresh, because of the $forceRefresh param of ->commit()
        $this->assertEquals(6, $this->getCollector()->getQueryCount());
    }

    /**
     * Tests if correct time is being returned.
     */
    public function testGetTime()
    {
        $imWithoutAliases = $this->getIndexManager('bar');
        $imWithoutAliases->getConnection()->setAutocommit(true);
        $imWithoutAliases->getRepository()->getById(3);

        $this->assertGreaterThan(0.0, $this->getCollector()->getTime(), 'Time should be greater than 0ms');
        $this->assertLessThan(1000.0, $this->getCollector()->getTime(), 'Time should be less than 1s');
    }

    /**
     * Tests if logged data seems correct
     */
    public function testCorrectDataLogged()
    {
        $this->markTestSkipped(
            'Skipped until this is fixed: https://github.com/elastic/elasticsearch-php/issues/1113'
        );

        $imWithoutAliases = $this->getIndexManager('bar');
        $imWithoutAliases->getConnection()->setAutocommit(true);
        $imWithoutAliases->getRepository()->getById(3);


        $queries = $this->getCollector()->getQueries();

        $lastQuery = end($queries[ElasticsearchProfiler::UNDEFINED_ROUTE]);
        $this->checkQueryParameters($lastQuery);

        $esHostAndPort = explode(':', $imWithoutAliases->getConnection()->getConnectionSettings()['hosts'][0]);

        $this->assertArraySubset(
            [
                "curlRequest" => "curl -XPOST 'http://".implode(':', $esHostAndPort)."/sineflow-esb-test-bar/_search?pretty=true' -d '{\"query\":{\"ids\":{\"values\":[3]}},\"version\":true}'",
                "senseRequest" => "POST /sineflow-esb-test-bar/_search\n{\"query\":{\"ids\":{\"values\":[3]}},\"version\":true}",
                "backtrace" => null,
                "scheme" => "http",
                "host" => $esHostAndPort[0],
                "port" => (int) $esHostAndPort[1],
                "path" => "/sineflow-esb-test-bar/_search",
            ],
            $lastQuery,
            'Logged data did not match expected data.'
        );
    }

    /**
     * Checks query parameters that are not static.
     *
     * @param array $query
     */
    public function checkQueryParameters($query)
    {
        $this->assertArrayHasKey('time', $query, 'Query should have time set.');
        $this->assertGreaterThan(0.0, $query['time'], 'Time should be greater than 0');

        $this->assertArrayHasKey('curlRequest', $query, 'Query should have curlRequest set.');
        $this->assertNotEmpty($query['curlRequest'], 'curlRequest should not be empty');

        $this->assertArrayHasKey('senseRequest', $query, 'Query should have senseRequest set.');
        $this->assertNotEmpty($query['senseRequest'], 'senseRequest should not be empty.');

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

    /**
     * @return ElasticsearchProfiler
     */
    private function getCollector()
    {
        $collector = $this->getContainer()->get('sfes.profiler');
        $collector->collect(new Request(), new Response());

        return $collector;
    }
}
