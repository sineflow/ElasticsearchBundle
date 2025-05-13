<?php

namespace Sineflow\ElasticsearchBundle\Tests\App\Fixture\Acme\FooBundle\Document;

use Sineflow\ElasticsearchBundle\Annotation as ES;
use Sineflow\ElasticsearchBundle\Attribute as SFES;
use Sineflow\ElasticsearchBundle\Document\AbstractDocument;

/**
 * @ES\Document;
 */
#[SFES\Document]
class Log extends AbstractDocument
{
    /**
     * @ES\Property(name="entry", type="keyword")
     */
    #[SFES\Property(
        name: 'entry',
        type: 'keyword',
    )]
    public ?string $entry = null;
}
