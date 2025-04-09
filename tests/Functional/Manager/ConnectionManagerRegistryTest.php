<?php

namespace Sineflow\ElasticsearchBundle\Tests\Functional\Manager;

use Sineflow\ElasticsearchBundle\Exception\InvalidConnectionManagerException;
use Sineflow\ElasticsearchBundle\Manager\ConnectionManager;
use Sineflow\ElasticsearchBundle\Manager\ConnectionManagerRegistry;
use Sineflow\ElasticsearchBundle\Tests\AbstractContainerAwareTestCase;

class ConnectionManagerRegistryTest extends AbstractContainerAwareTestCase
{
    public function testGet(): void
    {
        /** @var ConnectionManagerRegistry $registry */
        $registry = $this->getContainer()->get(ConnectionManagerRegistry::class);

        $connection = $registry->get('default');
        $this->assertInstanceOf(ConnectionManager::class, $connection);

        $connection = $registry->get('backup_conn');
        $this->assertInstanceOf(ConnectionManager::class, $connection);

        $this->expectException(InvalidConnectionManagerException::class);
        $registry->get('blah');
    }

    public function testGetAll(): void
    {
        /** @var ConnectionManagerRegistry $registry */
        $registry = $this->getContainer()->get(ConnectionManagerRegistry::class);

        $connections = $registry->getAll();
        foreach ($connections as $connection) {
            $this->assertInstanceOf(ConnectionManager::class, $connection);
        }
    }
}
