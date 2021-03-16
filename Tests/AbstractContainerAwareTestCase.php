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
    private $cachedContainer;

    /**
     * {@inheritDoc}
     */
    protected function setUp(): void
    {
        $this->cachedContainer = null;
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
        if (!$this->cachedContainer) {
            static::bootKernel($kernelOptions);
            $this->cachedContainer = static::$kernel->getContainer();
        }

        return $this->cachedContainer;
    }
}
