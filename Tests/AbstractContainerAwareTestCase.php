<?php

namespace Sineflow\ElasticsearchBundle\Tests;

use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Base test which gives access to container
 */
abstract class AbstractContainerAwareTestCase extends WebTestCase
{
    /**
     * @var ContainerInterface
     */
    private $container;

    /**
     * Returns service container.
     *
     * @param bool  $reinitialize  Force kernel reinitialization.
     * @param array $kernelOptions Options used passed to kernel if it needs to be initialized.
     *
     * @return ContainerInterface
     */
    protected function getContainer($reinitialize = false, $kernelOptions = [])
    {
        if (!$this->container || $reinitialize) {
            static::bootKernel($kernelOptions);
            $this->container = static::$kernel->getContainer();
        }

        return $this->container;
    }
}
