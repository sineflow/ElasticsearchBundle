<?php

declare(strict_types=1);

namespace Sineflow\ElasticsearchBundle\Tests\Unit\Annotation;

use PHPUnit\Framework\TestCase;
use Sineflow\ElasticsearchBundle\Annotation\Document;

/**
 * Class DocumentTest
 */
final class DocumentTest extends TestCase
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

        $this->assertSame(
            [
                'dynamic' => 'strict',
                'foo'     => 'bar',
            ],
            $doc->dump(),
            'All and only options should be dumped'
        );
    }
}
