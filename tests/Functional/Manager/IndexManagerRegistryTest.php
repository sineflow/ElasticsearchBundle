<?php

namespace Sineflow\ElasticsearchBundle\Tests\Functional\Manager;

use Sineflow\ElasticsearchBundle\Exception\InvalidIndexManagerException;
use Sineflow\ElasticsearchBundle\Manager\IndexManager;
use Sineflow\ElasticsearchBundle\Manager\IndexManagerRegistry;
use Sineflow\ElasticsearchBundle\Tests\AbstractContainerAwareTestCase;
use Sineflow\ElasticsearchBundle\Tests\App\Fixture\Acme\BarBundle\Document\Product;

/**
 * Class IndexManagerTest
 */
class IndexManagerRegistryTest extends AbstractContainerAwareTestCase
{
    public function testGet(): void
    {
        /** @var IndexManagerRegistry $registry */
        $registry = $this->getContainer()->get(IndexManagerRegistry::class);

        $im = $registry->get('customer');
        $this->assertInstanceOf(IndexManager::class, $im);

        $this->expectException(InvalidIndexManagerException::class);
        $im = $registry->get('blah');

        $this->expectException(InvalidIndexManagerException::class);
        $im = $registry->get('nonexisting');
    }

    public function testGetByClass(): void
    {
        $registry = $this->getContainer()->get(IndexManagerRegistry::class);

        $product = new Product();
        $im = $registry->getByClass($product::class);
        $this->assertInstanceOf(IndexManager::class, $im);
        $this->assertEquals('bar', $im->getManagerName());
    }

    public function testGetByEntity(): void
    {
        $registry = $this->getContainer()->get(IndexManagerRegistry::class);

        $product = new Product();
        $im = $registry->getByEntity($product);
        $this->assertInstanceOf(IndexManager::class, $im);
        $this->assertEquals('bar', $im->getManagerName());
    }
}
