<?php

namespace Sineflow\ElasticsearchBundle\Tests\App\Fixture\Acme\FooBundle\Document;

use Sineflow\ElasticsearchBundle\Attribute as SFES;
use Sineflow\ElasticsearchBundle\Document\AbstractDocument;

#[SFES\Document]
class Log extends AbstractDocument
{
    #[SFES\Property(
        name: 'entry',
        type: 'keyword',
    )]
    public ?string $entry = null;
}
