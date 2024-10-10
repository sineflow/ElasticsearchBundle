<?php

namespace Sineflow\ElasticsearchBundle\Tests\Functional\Document\Provider;

use Sineflow\ElasticsearchBundle\Document\Provider\ElasticsearchProvider;
use Sineflow\ElasticsearchBundle\Document\Provider\ProviderRegistry;
use Sineflow\ElasticsearchBundle\Tests\AbstractElasticsearchTestCase;
use Sineflow\ElasticsearchBundle\Tests\App\Fixture\Acme\BarBundle\Document\Product;
use Sineflow\ElasticsearchBundle\Tests\App\Fixture\Acme\FooBundle\Document\Customer;
use Sineflow\ElasticsearchBundle\Tests\App\Fixture\Acme\FooBundle\Document\Log;
use Sineflow\ElasticsearchBundle\Tests\App\Fixture\Acme\FooBundle\Document\Order;
use Sineflow\ElasticsearchBundle\Tests\App\Fixture\Acme\FooBundle\Document\Provider\CustomerProvider;
use Sineflow\ElasticsearchBundle\Tests\App\Fixture\Acme\FooBundle\Document\Provider\OrderProvider;

class ProviderRegistryTest extends AbstractElasticsearchTestCase
{
    /**
     * @var ProviderRegistry
     */
    private $providerRegistry;

    /**
     * {@inheritdoc}
     */
    protected function getDataArray()
    {
        return [
            'bar' => [
                [
                    '_id'   => 'product1',
                    'title' => 'Product1',
                ],
            ],
            'customer' => [
                [
                    '_id'  => 'customer1',
                    'name' => 'Customer1',
                ],
            ],
            'order' => [
                [
                    '_id'        => 'order1',
                    'order_time' => 11112222,
                ],
            ],
        ];
    }

    protected function setUp(): void
    {
        parent::setUp();

        $this->providerRegistry = $this->getContainer()->get(ProviderRegistry::class);
    }

    public function testGetProviderForEntity(): void
    {
        $this->assertInstanceOf(CustomerProvider::class, $this->providerRegistry->getCustomProviderForEntity(Customer::class));
        $this->assertInstanceOf(OrderProvider::class, $this->providerRegistry->getCustomProviderForEntity(Order::class));
        $this->assertNull($this->providerRegistry->getCustomProviderForEntity(Log::class));
        $this->assertNull($this->providerRegistry->getCustomProviderForEntity(Product::class));
    }

    public function testGetSelfProviderForEntity(): void
    {
        // Get the index managers to trigger the creation of the mock indices
        $this->getIndexManager('customer');
        $this->getIndexManager('bar');
        $this->getIndexManager('order');

        $customerProvider = $this->providerRegistry->getSelfProviderForEntity(Customer::class);
        $this->assertInstanceOf(ElasticsearchProvider::class, $customerProvider);
        $this->assertSame('Customer1', $customerProvider->getDocument('customer1')['name']);

        $orderProvider = $this->providerRegistry->getSelfProviderForEntity(Order::class);
        $this->assertInstanceOf(ElasticsearchProvider::class, $orderProvider);
        $this->assertSame(11112222, $orderProvider->getDocument('order1')['order_time']);

        $productProvider = $this->providerRegistry->getSelfProviderForEntity(Product::class);
        $this->assertInstanceOf(ElasticsearchProvider::class, $productProvider);
        $this->assertSame('Product1', $productProvider->getDocument('product1')['title']);
    }
}
