<?php

namespace Sineflow\ElasticsearchBundle\Tests\App\Fixture\Acme\FooBundle\Document;

use Sineflow\ElasticsearchBundle\Annotation as ES;
use Sineflow\ElasticsearchBundle\Attribute as SFES;
use Sineflow\ElasticsearchBundle\Document\AbstractDocument;
use Sineflow\ElasticsearchBundle\Tests\App\Fixture\Acme\FooBundle\Enum\CustomerTypeEnum;

/**
 * @ES\Document
 */
#[SFES\Document]
class EntityWithInvalidEnum extends AbstractDocument
{
    /**
     * @ES\Property(
     *  name="enum_test",
     *  type="string",
     *  enumType=nonExistingEnumClass
     * )
     */
    #[SFES\Property(
        name: 'enum_test',
        type: 'string',
        enumType: 'nonExistingEnumClass',
    )]
    public ?CustomerTypeEnum $enumTest = null;
}
