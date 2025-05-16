<?php

namespace Sineflow\ElasticsearchBundle\Tests\App\Fixture\Acme\BarBundle\Document;

use Sineflow\ElasticsearchBundle\Annotation as ES;
use Sineflow\ElasticsearchBundle\Attribute as SFES;
use Sineflow\ElasticsearchBundle\Document\ObjectInterface;
use Sineflow\ElasticsearchBundle\Result\ObjectIterator;

/**
 * Category document for testing.
 *
 * @ES\DocObject
 */
#[SFES\DocObject]
class ObjCategory implements ObjectInterface
{
    /**
     * @var string Field without a SFES attribute or ES annotation - should not be indexed.
     */
    public string $withoutAnnotation;

    /**
     * @ES\Property(type="integer", name="id")
     */
    #[SFES\Property(
        name: 'id',
        type: 'integer',
    )]
    public ?int $id = null;

    /**
     * @ES\Property(type="keyword", name="title")
     */
    #[SFES\Property(
        name: 'title',
        type: 'keyword',
    )]
    public ?string $title = null;

    /**
     * @var ObjTag[]|ObjectIterator<ObjTag>
     *
     * @ES\Property(type="object", name="tags", multiple=true, objectName="AcmeBarBundle:ObjTag")
     */
    #[SFES\Property(
        name: 'tags',
        type: 'object',
        objectName: ObjTag::class,
        multiple: true,
    )]
    public ObjectIterator|array $tags = [];
}
