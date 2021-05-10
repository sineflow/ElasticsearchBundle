<?php

namespace Sineflow\ElasticsearchBundle\Tests\Functional\Manager;

use Sineflow\ElasticsearchBundle\Exception\InvalidIndexManagerException;
use Sineflow\ElasticsearchBundle\Manager\IndexManagerInterface;
use Sineflow\ElasticsearchBundle\Manager\IndexManagerRegistry;
use Sineflow\ElasticsearchBundle\Tests\AbstractContainerAwareTestCase;
use Sineflow\ElasticsearchBundle\Tests\App\fixture\Acme\BarBundle\Document\Product;

/**
 * Class IndexManagerTest
 */
class IndexManagerRegistryTest extends AbstractContainerAwareTestCase
{
    public function testGet()
    {
        /** @var IndexManagerRegistry $registry */
        $registry = $this->getContainer()->get('sfes.index_manager_registry');

        $im = $registry->get('customer');
        $this->assertInstanceOf(IndexManagerInterface::class, $im);

        $this->expectException(InvalidIndexManagerException::class);
        $im = $registry->get('blah');

        $this->expectException(InvalidIndexManagerException::class);
        $im = $registry->get('nonexisting');
    }

    public function testGetByEntity()
    {
        $registry = $this->getContainer()->get('sfes.index_manager_registry');

        $product = new Product();
        $im = $registry->getByEntity($product);
        $this->assertInstanceOf(IndexManagerInterface::class, $im);
        $this->assertEquals('bar', $im->getManagerName());
    }
}
