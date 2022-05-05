<?php
// phpcs:ignoreFile

namespace Sineflow\ElasticsearchBundle\Tests;

use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\DependencyInjection\ContainerInterface;

// Since symfony/framework-bundle 5.3, there is already a getContainer() method in KernelTestCase,
// which makes AbstractContainerAwareTestCase obsolete
if (method_exists(KernelTestCase::class, 'getContainer')) {
    abstract class AbstractContainerAwareTestCase extends KernelTestCase {}

    return;
}

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
    protected function getContainer(array $kernelOptions = []): ContainerInterface
    {
        if (!$this->cachedContainer) {
            static::bootKernel($kernelOptions);
            // gets the special container that allows fetching private services
            $this->cachedContainer = static::$container;
        }

        return $this->cachedContainer;
    }
}
