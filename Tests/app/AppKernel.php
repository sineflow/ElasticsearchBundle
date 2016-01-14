<?php

use Symfony\Component\Config\Loader\LoaderInterface;
use Symfony\Component\HttpKernel\Kernel;

/**
 * AppKernel class.
 */
class AppKernel extends Kernel
{
    /**
     * Register bundles.
     *
     * @return array
     */
    public function registerBundles()
    {
        return [
            new Symfony\Bundle\FrameworkBundle\FrameworkBundle(),
            new Sineflow\ElasticsearchBundle\SineflowElasticsearchBundle(),
            new Sineflow\ElasticsearchBundle\Tests\app\fixture\Acme\BarBundle\AcmeBarBundle(),
            new Sineflow\ElasticsearchBundle\Tests\app\fixture\Acme\FooBundle\AcmeFooBundle(),
        ];
    }

    /**
     * Register container configuration.
     *
     * @param LoaderInterface $loader
     */
    public function registerContainerConfiguration(LoaderInterface $loader)
    {
        $loader->load(__DIR__ . '/config/config_' . $this->getEnvironment() . '.yml');
    }
}
