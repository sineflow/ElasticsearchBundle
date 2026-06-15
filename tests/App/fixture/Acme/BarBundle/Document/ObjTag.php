<?php

namespace Sineflow\ElasticsearchBundle\Tests\App\Fixture\Acme\BarBundle\Document;

use Sineflow\ElasticsearchBundle\Attribute as SFES;
use Sineflow\ElasticsearchBundle\Document\ObjectInterface;

/**
 * Tag document for testing.
 */
#[SFES\DocObject]
class ObjTag implements ObjectInterface
{
    #[SFES\Property(
        name: 'tagname',
        type: 'text',
    )]
    public ?string $tagName = null;
}
