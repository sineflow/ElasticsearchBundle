<?php

namespace Sineflow\ElasticsearchBundle\Tests\Unit\DTO;

use Jchook\AssertThrows\AssertThrows;
use PHPUnit\Framework\TestCase;
use Sineflow\ElasticsearchBundle\DTO\IndicesToDocumentClasses;

/**
 * Class IndicesToDocumentClassesTest
 */
class IndicesToDocumentClassesTest extends TestCase
{
    use AssertThrows;

    public function testGetSet(): void
    {
        $obj = new IndicesToDocumentClasses();

        $obj->set('my_real_index', 'App:Entity');
        $this->assertSame('App:Entity', $obj->get('my_real_index'));

        $this->assertThrows(\InvalidArgumentException::class, static function () use ($obj): void {
            $obj->set(null, 'App:Entity');
        });

        $this->assertThrows(\InvalidArgumentException::class, static function () use ($obj): void {
            $obj->get('non_existing_index');
        });

        $obj = new IndicesToDocumentClasses();

        $obj->set(null, 'App:Entity');
        $this->assertSame('App:Entity', $obj->get('second_real_index'));
        $this->assertSame('App:Entity', $obj->get('non_existing_index'));

        $this->assertThrows(\InvalidArgumentException::class, static function () use ($obj): void {
            $obj->set('my_real_index', 'App:Entity');
        });
    }
}
