<?php

namespace Sineflow\ElasticsearchBundle\Tests\app\fixture\Acme\BarBundle\Document;

use Sineflow\ElasticsearchBundle\Annotation as ES;
use Sineflow\ElasticsearchBundle\Document\ObjectInterface;

/**
 * Category document for testing.
 *
 * @ES\Object
 */
class ObjCategory implements ObjectInterface
{
    /**
     * @var string Field without ESB annotation, should not be indexed.
     */
    public $withoutAnnotation;

    /**
     * @var int
     * @ES\Property(type="integer", name="id")
     */
    public $id;

    /**
     * @var string
     * @ES\Property(type="string", name="title", options={"index"="not_analyzed"})
     */
    public $title;

    /**
     * @var ObjTag[]
     * @ES\Property(type="object", name="tags", multiple=true, objectName="AcmeBarBundle:ObjTag")
     */
    public $tags;

}
