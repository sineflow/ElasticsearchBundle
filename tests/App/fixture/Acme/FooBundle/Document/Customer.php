<?php

namespace Sineflow\ElasticsearchBundle\Tests\App\Fixture\Acme\FooBundle\Document;

use Sineflow\ElasticsearchBundle\Annotation as ES;
use Sineflow\ElasticsearchBundle\Attribute as SFES;
use Sineflow\ElasticsearchBundle\Document\AbstractDocument;
use Sineflow\ElasticsearchBundle\Tests\App\Fixture\Acme\FooBundle\Document\Provider\CustomerProvider;
use Sineflow\ElasticsearchBundle\Tests\App\Fixture\Acme\FooBundle\Enum\CustomerTypeEnum;

/**
 * @ES\Document(
 *     providerClass=CustomerProvider::class
 * )
 */
#[SFES\Document(
    providerClass: CustomerProvider::class,
)]
class Customer extends AbstractDocument
{
    /**
     * Test adding raw mapping.
     *
     * @ES\Property(name="name", type="keyword")
     */
    #[SFES\Property(
        name: 'name',
        type: 'keyword',
    )]
    public string $name;

    /**
     * Test adding raw mapping.
     *
     * @ES\Property(
     *  name="customer_type",
     *  type="integer",
     *  enumType=Sineflow\ElasticsearchBundle\Tests\App\Fixture\Acme\FooBundle\Enum\CustomerTypeEnum::class
     * )
     */
    #[SFES\Property(
        name: 'customer_type',
        type: 'integer',
        enumType: CustomerTypeEnum::class,
    )]
    public ?CustomerTypeEnum $customerType = null;

    /**
     * @var bool
     *
     * @ES\Property(name="active", type="boolean")
     */
    #[SFES\Property(
        name: 'active',
        type: 'boolean',
    )]
    private $active;

    public function isActive()
    {
        return $this->active;
    }

    public function setActive($active): void
    {
        $this->active = $active;
    }
}
