<?php

namespace Sineflow\ElasticsearchBundle\Tests\Unit\Annotation;

use PHPUnit\Framework\TestCase;
use Sineflow\ElasticsearchBundle\Annotation\Property;

/**
 * Class PropertyTest
 */
class PropertyTest extends TestCase
{
    /**
     * Tests if values are dumped correctly for mapping.
     */
    public function testDump()
    {
        $type = new Property();

        $type->name = 'myprop';
        $type->type = 'mytype';
        $type->multilanguage = false;
        $type->objectName = 'foo/bar';
        $type->multiple = null;
        $type->options = [
            'type' => 'this should not be set here',
            'analyzer' => 'standard',
            'foo' => 'bar',
        ];
        $type->foo = 'bar';

        $this->assertEquals(
            [
                'analyzer' => 'standard',
                'foo' => 'bar',
                'type' => 'mytype',
            ],
            $type->dump(),
            'Properties should be filtered'
        );
    }

    /**
     * Test if language placeholders are correctly replaced
     */
    public function testDumpML()
    {
        $type = new Property();

        $type->name = 'myprop';
        $type->type = 'mytype';
        $type->multilanguage = true;
        $type->objectName = 'foo/bar';
        $type->multiple = null;
        $type->options = [
            'copy_to' => '{lang}_all',
            'analyzer' => '{lang}_analyzer',
            'fields' => [
                'ngram' => [
                    'analyzer' => '{lang}_analyzer',
                ],
            ],
        ];

        $settings = [
            'language' => 'en',
            'indexAnalyzers' => [
                'default_analyzer' => [
                    'type' => 'standard',
                ],
                'en_analyzer' => [
                    'type' => 'standard',
                ],
            ],
        ];

        $this->assertEquals(
            [
                'copy_to' => 'en_all',
                'analyzer' => 'en_analyzer',
                'fields' =>
                    [
                        'ngram' =>
                            [
                                'analyzer' => 'en_analyzer',
                            ],
                    ],
                'type' => 'mytype',
            ],
            $type->dump($settings),
            'Language placeholders not correctly replaced'
        );
    }

    /**
     * Test that exception is thrown when language is specified but there are no index analyzers set
     *
     * @expectedException \InvalidArgumentException
     */
    public function testDumpNoAnalyzersException()
    {
        $type = new Property();

        $type->name = 'myprop';
        $type->type = 'mytype';
        $type->multilanguage = false;
        $type->objectName = 'foo/bar';
        $type->multiple = null;
        $type->options = [
            'analyzer' => '{lang}_analyzer',
        ];

        $settings = [
            'language' => 'en',
        ];

        $type->dump($settings);
    }

    /**
     * Test that exception is thrown when no default language analyzer is set
     *
     * @expectedException \LogicException
     */
    public function testDumpNoDefaultException()
    {
        $type = new Property();

        $type->name = 'myprop';
        $type->type = 'mytype';
        $type->multilanguage = false;
        $type->objectName = 'foo/bar';
        $type->multiple = null;
        $type->options = [
            'analyzer' => '{lang}_analyzer',
        ];

        $settings = [
            'language' => 'en',
            'indexAnalyzers' => [
                'en_analyzer' => [
                    'type' => 'standard',
                ],
            ],
        ];

        $type->dump($settings);
    }
}
