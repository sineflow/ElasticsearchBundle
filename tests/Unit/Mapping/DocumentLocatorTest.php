<?php

namespace Sineflow\ElasticsearchBundle\Tests\Unit\Mapping;

use PHPUnit\Framework\TestCase;
use Sineflow\ElasticsearchBundle\Mapping\DocumentLocator;

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
     *
     * @return array
     */
    public function getTestResolveClassNameDataProvider()
    {
        $out = [
            [
                'AcmeBarBundle:Product',
                'Sineflow\ElasticsearchBundle\Tests\App\Fixture\Acme\BarBundle\Document\Product',
            ],
            [
                'AcmeFooBundle:Product',
                'Sineflow\ElasticsearchBundle\Tests\App\Fixture\Acme\FooBundle\Document\Product',
            ],
            [
                'Sineflow\ElasticsearchBundle\Tests\App\Fixture\Acme\BarBundle\Document\Product',
                'Sineflow\ElasticsearchBundle\Tests\App\Fixture\Acme\BarBundle\Document\Product',
            ],
        ];

        return $out;
    }

    /**
     * Data provider
     *
     * @return array
     */
    public function getShortClassNameDataProvider()
    {
        $out = [
            [
                'Sineflow\ElasticsearchBundle\Tests\App\Fixture\Acme\BarBundle\Document\Product',
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
     *
     * @return array
     */
    public function getShortClassNameExceptionsDataProvider()
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
    public function testGetAllDocumentDirs()
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
    public function testResolveClassName($className, $expectedClassName)
    {
        $this->assertEquals($expectedClassName, $this->locator->resolveClassName($className));
    }
}
