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

    public function testGetSet()
    {
        $obj = new IndicesToDocumentClasses();

        $obj->set('my_real_index', 'AppBundle:Entity');
        $this->assertEquals('AppBundle:Entity', $obj->get('my_real_index'));

        $this->assertThrows(\InvalidArgumentException::class, function () use ($obj) {
            $obj->set(null, 'AppBundle:Entity');
        });

        $this->assertThrows(\InvalidArgumentException::class, function () use ($obj) {
            $obj->get('non_existing_index');
        });

        $obj = new IndicesToDocumentClasses();

        $obj->set(null, 'AppBundle:Entity');
        $this->assertEquals('AppBundle:Entity', $obj->get('second_real_index'));
        $this->assertEquals('AppBundle:Entity', $obj->get('non_existing_index'));

        $this->assertThrows(\InvalidArgumentException::class, function () use ($obj) {
            $obj->set('my_real_index', 'AppBundle:Entity');
        });
    }
}
