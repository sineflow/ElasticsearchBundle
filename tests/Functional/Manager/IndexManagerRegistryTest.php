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
    public function testGet()
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

    public function testGetByClass()
    {
        $registry = $this->getContainer()->get(IndexManagerRegistry::class);

        $product = new Product();
        $im = $registry->getByClass(get_class($product));
        $this->assertInstanceOf(IndexManager::class, $im);
        $this->assertEquals('bar', $im->getManagerName());
    }

    public function testGetByEntity()
    {
        $registry = $this->getContainer()->get(IndexManagerRegistry::class);

        $product = new Product();
        $im = $registry->getByEntity($product);
        $this->assertInstanceOf(IndexManager::class, $im);
        $this->assertEquals('bar', $im->getManagerName());
    }
}
