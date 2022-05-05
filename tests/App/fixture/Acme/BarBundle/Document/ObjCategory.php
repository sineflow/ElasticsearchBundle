<?php

namespace Sineflow\ElasticsearchBundle\Tests\App\Fixture\Acme\BarBundle\Document;

use Sineflow\ElasticsearchBundle\Annotation as ES;
use Sineflow\ElasticsearchBundle\Document\ObjectInterface;

/**
 * Category document for testing.
 *
 * @ES\DocObject
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
     * @ES\Property(type="keyword", name="title")
     */
    public $title;

    /**
     * @var ObjTag[]
     * @ES\Property(type="object", name="tags", multiple=true, objectName="AcmeBarBundle:ObjTag")
     */
    public $tags;
}
