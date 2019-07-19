<?php

namespace Sineflow\ElasticsearchBundle\Tests\Functional\Subscriber;

use Sineflow\ElasticsearchBundle\Finder\Finder;
use Sineflow\ElasticsearchBundle\Tests\AbstractElasticsearchTestCase;
use Sineflow\ElasticsearchBundle\Tests\App\fixture\Acme\FooBundle\Document\Customer;
use Sineflow\ElasticsearchBundle\Tests\App\fixture\Acme\FooBundle\Document\Log;

/**
 * Class EntityTrackerSubscriberTest
 */
class EntityTrackerSubscriberTest extends AbstractElasticsearchTestCase
{
    /**
     * Test populating persisted entity ids after a bulk operation with several operations
     */
    public function testPersistWithSeveralBulkOps()
    {
        $converter = $this->getContainer()->get('sfes.document_converter');

        $imWithAliases = $this->getIndexManager('customer');

        // Another index manager on the same connection
        $imNoAliases = $this->getIndexManager('bar');

        // Make sure both index managers share the same connection object instance
        $this->assertSame($imWithAliases->getConnection(), $imNoAliases->getConnection());

        $imNoAliases->getConnection()->setAutocommit(false);

        // Index manager on another connection
        $backupIm = $this->getIndexManager('backup');
        $backupIm->getConnection()->setAutocommit(false);

        // Make sure this index manager has a separate connection manager
        $this->assertNotSame($imWithAliases->getConnection(), $backupIm->getConnection());


        // Persist raw document - ignored by the subscriber as there's no entity to update
        $rawCustomer = new Customer();
        $rawCustomer->name = 'firstRaw';
        $documentArray = $converter->convertToArray($rawCustomer);
        $imWithAliases->persistRaw($documentArray);

        // Persist entity - handled by the subscriber
        $customer = new Customer();
        $customer->name = 'batman';
        $imWithAliases->persist($customer);

        // Persist another raw document - ignored by the subscriber as there's no entity to update
        $secondRawCustomer = new Customer();
        $secondRawCustomer->name = 'secondRaw';
        $documentArray = $converter->convertToArray($secondRawCustomer);
        $imWithAliases->persistRaw($documentArray);

        // Persist an entity to another connection to make sure the subscriber handles the 2 commits independently
        $log = new Log();
        $log->id = 123;
        $log->entry = 'test log entry';
        $backupIm->persist($log);

        // Persist another entity to the first connection - handled by the subscriber
        $secondCustomer = new Customer();
        $secondCustomer->id = '555';
        $secondCustomer->name = 'joker';
        $imWithAliases->persist($secondCustomer);

        $this->assertNull($rawCustomer->id);
        $this->assertNull($customer->id);
        $this->assertNull($secondRawCustomer->id);
        $this->assertEquals('555', $secondCustomer->id);


        $imWithAliases->getConnection()->commit();
        $backupIm->getConnection()->commit();

        $this->assertNull($rawCustomer->id, 'id should not have been set');
        $this->assertNotNull($customer->id, 'id should have been set');
        $this->assertNull($secondRawCustomer->id, 'id should not have been set');
        $this->assertEquals('555', $secondCustomer->id);
        $this->assertEquals(123, $log->id);

        // Get the customer from ES by name
        $finder = $this->getContainer()->get('sfes.finder');
        $searchBody = ['query' => ['match' => ['name' => 'batman']]];
        $docs = $finder->find(['AcmeFooBundle:Customer'], $searchBody, Finder::RESULTS_OBJECT);
        $this->assertCount(1, $docs);
        $retrievedCustomer = $docs->current();

        // Make sure that the correct id was assigned to the object, not the id of another customer
        $this->assertEquals($customer->id, $retrievedCustomer->id);
    }
}
