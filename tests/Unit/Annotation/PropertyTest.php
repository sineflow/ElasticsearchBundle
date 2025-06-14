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
    public function testDump(): void
    {
        $type = new Property();

        $type->name = 'myprop';
        $type->type = 'mytype';
        $type->multilanguage = false;
        $type->objectName = 'foo/bar';
        $type->multiple = false;
        $type->options = [
            'type'     => 'this should not be set here',
            'analyzer' => 'standard',
            'foo'      => 'bar',
        ];
        $type->enumType = 'bar';

        $this->assertSame(
            [
                'type'     => 'mytype',
                'analyzer' => 'standard',
                'foo'      => 'bar',
            ],
            $type->dump(),
            'Properties should be filtered'
        );
    }

    /**
     * Test if language placeholders are correctly replaced
     */
    public function testDumpML(): void
    {
        $type = new Property();

        $type->name = 'myprop';
        $type->type = 'mytype';
        $type->multilanguage = true;
        $type->objectName = 'foo/bar';
        $type->multiple = false;
        $type->options = [
            'copy_to'  => '{lang}_all',
            'analyzer' => '{lang}_analyzer',
            'fields'   => [
                'ngram' => [
                    'analyzer' => '{lang}_analyzer',
                ],
            ],
        ];

        $settings = [
            'language'       => 'en',
            'indexAnalyzers' => [
                'default_analyzer' => [
                    'type' => 'standard',
                ],
                'en_analyzer' => [
                    'type' => 'standard',
                ],
            ],
        ];

        $this->assertSame(
            [
                'copy_to'  => 'en_all',
                'analyzer' => 'en_analyzer',
                'fields'   => [
                    'ngram' => [
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
     */
    public function testDumpNoAnalyzersException(): void
    {
        $type = new Property();

        $type->name = 'myprop';
        $type->type = 'mytype';
        $type->multilanguage = false;
        $type->objectName = 'foo/bar';
        $type->multiple = false;
        $type->options = [
            'analyzer' => '{lang}_analyzer',
        ];

        $settings = [
            'language' => 'en',
        ];

        $this->expectException(\InvalidArgumentException::class);
        $type->dump($settings);
    }

    /**
     * Test that exception is thrown when no default language analyzer is set
     */
    public function testDumpNoDefaultException(): void
    {
        $type = new Property();

        $type->name = 'myprop';
        $type->type = 'mytype';
        $type->multilanguage = false;
        $type->objectName = 'foo/bar';
        $type->multiple = false;
        $type->options = [
            'analyzer' => '{lang}_analyzer',
        ];

        $settings = [
            'language'       => 'en',
            'indexAnalyzers' => [
                'en_analyzer' => [
                    'type' => 'standard',
                ],
            ],
        ];

        $this->expectException(\LogicException::class);
        $type->dump($settings);
    }
}
