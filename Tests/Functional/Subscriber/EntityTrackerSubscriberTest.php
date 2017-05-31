<?php

namespace Sineflow\ElasticsearchBundle\Tests\Functional\Subscriber;

use Sineflow\ElasticsearchBundle\Finder\Finder;
use Sineflow\ElasticsearchBundle\Tests\AbstractElasticsearchTestCase;
use Sineflow\ElasticsearchBundle\Tests\app\fixture\Acme\FooBundle\Document\Customer;

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

        $imWithAliases = $this->getIndexManager('foo');
        $imWithAliases->getConnection()->setAutocommit(false);

        $rawCustomer = new Customer();
        $rawCustomer->name = 'firstRaw';
        $documentArray = $converter->convertToArray($rawCustomer);
        $imWithAliases->persistRaw('AcmeFooBundle:Customer', $documentArray);

        $customer = new Customer();
        $customer->name = 'batman';
        $imWithAliases->persist($customer);

        $secondRawCustomer = new Customer();
        $secondRawCustomer->name = 'secondRaw';
        $documentArray = $converter->convertToArray($secondRawCustomer);
        $imWithAliases->persistRaw('AcmeFooBundle:Customer', $documentArray);

        $secondCustomer = new Customer();
        $secondCustomer->id = '555';
        $secondCustomer->name = 'joker';
        $imWithAliases->persist($secondCustomer);

        $this->assertNull($rawCustomer->id);
        $this->assertNull($customer->id);
        $this->assertNull($secondRawCustomer->id);
        $this->assertEquals('555', $secondCustomer->id);

        $imWithAliases->getConnection()->commit();

        $this->assertNull($rawCustomer->id, 'id should not have been set');
        $this->assertNotNull($customer->id, 'id should have been set');
        $this->assertNull($secondRawCustomer->id, 'id should not have been set');
        $this->assertEquals('555', $secondCustomer->id);

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
