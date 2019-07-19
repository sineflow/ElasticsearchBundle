<?php

namespace Sineflow\ElasticsearchBundle\Tests;

use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Base test which gives access to container
 */
abstract class AbstractContainerAwareTestCase extends KernelTestCase
{
    /**
     * @var ContainerInterface
     */
    private $container;

    /**
     * {@inheritDoc}
     */
    protected function setUp()
    {
        $this->container = null;
    }

    /**
     * Returns service container.
     *
     * @param array $kernelOptions Options used passed to kernel if it needs to be initialized.
     *
     * @return ContainerInterface
     */
    protected function getContainer($kernelOptions = [])
    {
        if (!$this->container) {
            static::bootKernel($kernelOptions);
            $this->container = static::$kernel->getContainer();
        }

        return $this->container;
    }
}
