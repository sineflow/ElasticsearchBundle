<?php

namespace Sineflow\ElasticsearchBundle\Tests\Unit;

use PHPUnit\Framework\TestCase;
use Sineflow\ElasticsearchBundle\SineflowElasticsearchBundle;
use Symfony\Component\DependencyInjection\Compiler\PassConfig;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\Finder\Finder;
use Symfony\Component\Finder\SplFileInfo;

/**
 * Unit test for Sineflow\ElasticsearchBundle.
 */
class ElasticsearchBundleTest extends TestCase
{
    /**
     * @var array List of passes, which should not be added to compiler.
     */
    protected $passesBlacklist = [];

    /**
     * Check whether all Passes in DependencyInjection/Compiler/ are added to container.
     */
    public function testPassesRegistered(): void
    {
        $container = new ContainerBuilder();
        $bundle = new SineflowElasticsearchBundle();
        $bundle->build($container);

        /** @var array $loadedPasses Array of class names of loaded passes */
        $loadedPasses = [];
        /** @var PassConfig $passConfig */
        $passConfig = $container->getCompiler()->getPassConfig();
        foreach ($passConfig->getPasses() as $pass) {
            $classPath = \explode('\\', $pass::class);
            $loadedPasses[] = \end($classPath);
        }

        $finder = new Finder();
        $finder->files()->in(__DIR__.'/../../src/DependencyInjection/Compiler/');

        /** @var $file SplFileInfo */
        foreach ($finder as $file) {
            $passName = \str_replace('.php', '', $file->getFilename());
            // Check whether pass is not blacklisted and not added by bundle.
            if (!\in_array($passName, $this->passesBlacklist)) {
                $this->assertContains(
                    $passName,
                    $loadedPasses,
                    \sprintf(
                        "Compiler pass '%s' is not added to container or not blacklisted in test.",
                        $passName
                    )
                );
            }
        }
    }
}
