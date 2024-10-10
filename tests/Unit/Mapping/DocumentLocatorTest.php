<?php

namespace Sineflow\ElasticsearchBundle\Tests\Unit\Mapping;

use PHPUnit\Framework\TestCase;
use Sineflow\ElasticsearchBundle\Mapping\DocumentLocator;
use Sineflow\ElasticsearchBundle\Tests\App\Fixture\Acme\BarBundle\Document\Product;

/**
 * Class DocumentLocatorTest
 */
class DocumentLocatorTest extends TestCase
{
    /**
     * @var DocumentLocator
     */
    protected $locator;

    protected function setUp(): void
    {
        $this->locator = new DocumentLocator([
            'AcmeBarBundle' => [
                'directory' => 'Tests/App/fixture/Acme/BarBundle/Document',
                'namespace' => 'Sineflow\ElasticsearchBundle\Tests\App\Fixture\Acme\BarBundle\Document',
            ],
            'AcmeFooBundle' => [
                'directory' => 'Tests/App/fixture/Acme/FooBundle/Document',
                'namespace' => 'Sineflow\ElasticsearchBundle\Tests\App\Fixture\Acme\FooBundle\Document',
            ],
        ]);
    }

    /**
     * Data provider
     */
    public function getTestResolveClassNameDataProvider(): array
    {
        $out = [
            [
                'AcmeBarBundle:Product',
                Product::class,
            ],
            [
                'AcmeFooBundle:Product',
                'Sineflow\ElasticsearchBundle\Tests\App\Fixture\Acme\FooBundle\Document\Product',
            ],
            [
                Product::class,
                Product::class,
            ],
        ];

        return $out;
    }

    /**
     * Data provider
     */
    public function getShortClassNameDataProvider(): array
    {
        $out = [
            [
                Product::class,
                'AcmeBarBundle:Product',
            ],
            [
                'Sineflow\ElasticsearchBundle\Tests\App\Fixture\Acme\FooBundle\Document\Product',
                'AcmeFooBundle:Product',
            ],
            [
                'AcmeBarBundle:Product',
                'AcmeBarBundle:Product',
            ],
        ];

        return $out;
    }

    /**
     * Data provider
     */
    public function getShortClassNameExceptionsDataProvider(): array
    {
        $out = [
            [
                'Sineflow\ElasticsearchBundle\Tests\App\Fixture\Acme\BarBundle\InvalidDocumentDir\Product',
            ],
            [
                'Sineflow\NonExistingBundle\Tests\App\Fixture\Acme\FooBundle\Document\Product',
            ],
            [
                'Blah',
            ],
        ];

        return $out;
    }

    /**
     * Tests getAllDocumentDirs
     */
    public function testGetAllDocumentDirs(): void
    {
        $this->assertCount(2, $this->locator->getAllDocumentDirs());
    }

    /**
     * Tests if correct namespace is returned.
     *
     * @param string $className
     * @param string $expectedClassName
     *
     * @dataProvider getTestResolveClassNameDataProvider
     */
    public function testResolveClassName($className, $expectedClassName): void
    {
        $this->assertEquals($expectedClassName, $this->locator->resolveClassName($className));
    }
}
