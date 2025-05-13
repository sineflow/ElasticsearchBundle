<?php

namespace Sineflow\ElasticsearchBundle\Tests\App\Fixture\Acme\BarBundle\Document;

use Sineflow\ElasticsearchBundle\Annotation as ES;
use Sineflow\ElasticsearchBundle\Attribute as SFES;
use Sineflow\ElasticsearchBundle\Document\ObjectInterface;

/**
 * Tag document for testing.
 *
 * @ES\DocObject
 */
#[SFES\DocObject]
class ObjTag implements ObjectInterface
{
    /**
     * @ES\Property(type="text", name="tagname")
     */
    #[SFES\Property(
        name: 'tagname',
        type: 'text',
    )]
    public ?string $tagName = null;
}
