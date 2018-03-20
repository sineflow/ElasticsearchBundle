<?php

namespace Sineflow\ElasticsearchBundle\Tests\Unit\DTO;

use PHPUnit\Framework\TestCase;
use Sineflow\ElasticsearchBundle\DTO\TypesToDocumentClasses;

/**
 * Class TypesToDocumentClassesTest
 */
class TypesToDocumentClassesTest extends TestCase
{

    public function testGetSet()
    {
        $obj = new TypesToDocumentClasses();

        $obj->set('my_real_index', 'my_real_type', 'AppBundle:Type');
        $res = $obj->get('my_real_index', 'my_real_type');
        $this->assertEquals('AppBundle:Type', $res);

        $obj->set(null, 'second_real_type', 'AppBundle:Type');
        $res = $obj->get('second_real_index', 'second_real_type');
        $this->assertEquals('AppBundle:Type', $res);

        $this->setExpectedException('InvalidArgumentException');
        $obj->get('non_existing_index', 'my_real_type');
    }
}
