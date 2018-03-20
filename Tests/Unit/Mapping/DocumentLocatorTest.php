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

    protected function setUp()
    {
        $this->locator = new DocumentLocator($this->getBundles());
    }

    /**
     * @return array
     */
    public function getTestResolveClassNameData()
    {
        $out = [
            [
                'AcmeBarBundle:Product',
                'Sineflow\ElasticsearchBundle\Tests\app\fixture\Acme\BarBundle\Document\Product',
            ],
            [
                'AcmeFooBundle:Product',
                'Sineflow\ElasticsearchBundle\Tests\app\fixture\Acme\FooBundle\Document\Product',
            ],
            [
                'Sineflow\ElasticsearchBundle\Tests\app\fixture\Acme\BarBundle\Document\Product',
                'Sineflow\ElasticsearchBundle\Tests\app\fixture\Acme\BarBundle\Document\Product',
            ]
        ];

        return $out;
    }

    /**
     * @return array
     */
    public function testGetShortClassNameData()
    {
        $out = [
            [
                'Sineflow\ElasticsearchBundle\Tests\app\fixture\Acme\BarBundle\Document\Product',
                'AcmeBarBundle:Product',
            ],
            [
                'Sineflow\ElasticsearchBundle\Tests\app\fixture\Acme\FooBundle\Document\Product',
                'AcmeFooBundle:Product',
            ],
            [
                'AcmeBarBundle:Product',
                'AcmeBarBundle:Product',
            ]
        ];

        return $out;
    }

    /**
     * @return array
     */
    public function testGetShortClassNameExceptionsData()
    {
        $out = [
            [
                'Sineflow\ElasticsearchBundle\Tests\app\fixture\Acme\BarBundle\InvalidDocumentDir\Product',
            ],
            [
                'Sineflow\NonExistingBundle\Tests\app\fixture\Acme\FooBundle\Document\Product',
            ],
            [
                'Blah',
            ]
        ];


        return $out;
    }
    /**
     * Tests setAllDocumentDir and getAllDocumentDir
     */
    public function testGetSetDocumentDir()
    {
        $this->locator->setDocumentDir('Doc');
        $this->assertEquals('Doc', $this->locator->getDocumentDir());
    }

    /**
     * Tests getAllDocumentDirs
     */
    public function testGetAllDocumentDirs()
    {
        $this->assertEquals(2, count($this->locator->getAllDocumentDirs()));
    }

    /**
     * Tests if correct namespace is returned.
     *
     * @param string $className
     * @param string $expectedClassName
     *
     * @dataProvider getTestResolveClassNameData
     */
    public function testResolveClassName($className, $expectedClassName)
    {
        $this->assertEquals($expectedClassName, $this->locator->resolveClassName($className));
    }

    /**
     * @param string $className
     * @param string $expectedShortClassName
     *
     * @dataProvider testGetShortClassNameData
     */
    public function testGetShortClassName($className, $expectedShortClassName)
    {
        $this->assertEquals($expectedShortClassName, $this->locator->getShortClassName($className));
    }

    /**
     * @param string $className
     *
     * @dataProvider testGetShortClassNameExceptionsData
     * @expectedException UnexpectedValueException
     */
    public function testGetShortClassNameExceptions($className)
    {
        $this->locator->getShortClassName($className);
    }

    /**
     * @return array
     */
    private function getBundles()
    {
        return [
            'AcmeFooBundle' => 'Sineflow\ElasticsearchBundle\Tests\app\fixture\Acme\FooBundle\AcmeFooBundle',
            'AcmeBarBundle' => 'Sineflow\ElasticsearchBundle\Tests\app\fixture\Acme\BarBundle\AcmeBarBundle'
        ];
    }
}
