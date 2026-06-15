<?php

namespace Sineflow\ElasticsearchBundle\Tests\App\Fixture\Acme\BarBundle\Document;

use Sineflow\ElasticsearchBundle\Attribute as SFES;
use Sineflow\ElasticsearchBundle\Document\ObjectInterface;
use Sineflow\ElasticsearchBundle\Result\ObjectIterator;

/**
 * Category document for testing.
 */
#[SFES\DocObject]
class ObjCategory implements ObjectInterface
{
    /**
     * @var string Field without a SFES attribute - should not be indexed.
     */
    public string $withoutAnnotation;

    #[SFES\Property(
        name: 'id',
        type: 'integer',
    )]
    public ?int $id = null;

    #[SFES\Property(
        name: 'title',
        type: 'keyword',
    )]
    public ?string $title = null;

    /**
     * @var ObjTag[]|ObjectIterator<ObjTag>
     */
    #[SFES\Property(
        name: 'tags',
        type: 'object',
        objectName: ObjTag::class,
        multiple: true,
    )]
    public ObjectIterator|array $tags = [];
}
