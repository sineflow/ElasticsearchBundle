<?php

namespace Sineflow\ElasticsearchBundle\Tests\App\Fixture\Acme\FooBundle\Document;

use Sineflow\ElasticsearchBundle\Attribute as SFES;
use Sineflow\ElasticsearchBundle\Document\AbstractDocument;
use Sineflow\ElasticsearchBundle\Tests\App\Fixture\Acme\FooBundle\Enum\CustomerTypeEnum;

#[SFES\Document]
class EntityWithInvalidEnum extends AbstractDocument
{
    #[SFES\Property(
        name: 'enum_test',
        type: 'string',
        enumType: 'nonExistingEnumClass',
    )]
    public ?CustomerTypeEnum $enumTest = null;
}
