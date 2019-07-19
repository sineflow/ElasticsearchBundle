<?php

namespace Sineflow\ElasticsearchBundle\Tests\App;

use Knp\Bundle\PaginatorBundle\KnpPaginatorBundle;
use Sineflow\ElasticsearchBundle\SineflowElasticsearchBundle;
use Sineflow\ElasticsearchBundle\Tests\App\fixture\Acme\BarBundle\AcmeBarBundle;
use Sineflow\ElasticsearchBundle\Tests\App\fixture\Acme\FooBundle\AcmeFooBundle;
use Symfony\Bundle\FrameworkBundle\FrameworkBundle;
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
        $bundles = [
            new FrameworkBundle(),
            new KnpPaginatorBundle(),
            new SineflowElasticsearchBundle(),
        ];

        if (in_array($this->getEnvironment(), ['test'], true)) {
            $bundles[] = new AcmeBarBundle();
            $bundles[] = new AcmeFooBundle();
        }

        return $bundles;
    }

    /**
     * Register container configuration.
     *
     * @param LoaderInterface $loader
     *
     * @throws \Exception
     */
    public function registerContainerConfiguration(LoaderInterface $loader)
    {
        $loader->load(__DIR__.'/config/config_'.$this->getEnvironment().'.yml');
    }
}
