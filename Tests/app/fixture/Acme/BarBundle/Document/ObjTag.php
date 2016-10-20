<?php

namespace Sineflow\ElasticsearchBundle\Tests\app\fixture\Acme\BarBundle\Document;

use Sineflow\ElasticsearchBundle\Annotation as ES;
use Sineflow\ElasticsearchBundle\Document\ObjectInterface;

/**
 * Tag document for testing.
 *
 * @ES\DocObject
 */
class ObjTag implements ObjectInterface
{
    /**
     * @var string
     * @ES\Property(type="string", name="tagname")
     */
    public $tagName;
}
