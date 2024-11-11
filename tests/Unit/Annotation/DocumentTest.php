<?php

namespace Sineflow\ElasticsearchBundle\Tests\Unit\Annotation;

use PHPUnit\Framework\TestCase;
use Sineflow\ElasticsearchBundle\Annotation\Document;

/**
 * Class DocumentTest
 */
class DocumentTest extends TestCase
{
    /**
     * Tests if values are dumped correctly
     */
    public function testDump(): void
    {
        $doc = new Document();

        $doc->repositoryClass = 'some class name';
        $doc->options = [
            'dynamic' => 'strict',
            'foo'     => 'bar',
        ];

        $this->assertEquals(
            [
                'dynamic' => 'strict',
                'foo'     => 'bar',
            ],
            $doc->dump(),
            'All and only options should be dumped'
        );
    }
}
