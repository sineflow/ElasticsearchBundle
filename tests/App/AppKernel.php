<?php

namespace Sineflow\ElasticsearchBundle\Tests\App;

use Knp\Bundle\PaginatorBundle\KnpPaginatorBundle;
use Sineflow\ElasticsearchBundle\SineflowElasticsearchBundle;
use Sineflow\ElasticsearchBundle\Tests\App\Fixture\Acme\BarBundle\AcmeBarBundle;
use Sineflow\ElasticsearchBundle\Tests\App\Fixture\Acme\FooBundle\AcmeFooBundle;
use Symfony\Bundle\FrameworkBundle\FrameworkBundle;
use Symfony\Component\Config\Loader\LoaderInterface;
use Symfony\Component\HttpKernel\Kernel;

class AppKernel extends Kernel
{
    /**
     * Register bundles.
     *
     * @return array
     */
    public function registerBundles(): iterable
    {
        $bundles = [
            new FrameworkBundle(),
            new KnpPaginatorBundle(),
            new SineflowElasticsearchBundle(),
        ];

        if (\in_array($this->getEnvironment(), ['test'], true)) {
            $bundles[] = new AcmeBarBundle();
            $bundles[] = new AcmeFooBundle();
        }

        return $bundles;
    }

    /**
     * Register container configuration.
     *
     * @throws \Exception
     */
    public function registerContainerConfiguration(LoaderInterface $loader): void
    {
        $loader->load(__DIR__.'/config/config_'.$this->getEnvironment().'.yml');
    }
}
