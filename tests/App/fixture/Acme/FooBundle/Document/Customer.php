<?php

namespace Sineflow\ElasticsearchBundle\Tests\App\Fixture\Acme\FooBundle\Document;

use Sineflow\ElasticsearchBundle\Annotation as ES;
use Sineflow\ElasticsearchBundle\Document\AbstractDocument;
use Sineflow\ElasticsearchBundle\Tests\App\Fixture\Acme\FooBundle\Document\Provider\CustomerProvider;
use Sineflow\ElasticsearchBundle\Tests\App\Fixture\Acme\FooBundle\Enum\CustomerTypeEnum;

/**
 * @ES\Document(
 *     providerClass=CustomerProvider::class
 * )
 */
class Customer extends AbstractDocument
{
    /**
     * Test adding raw mapping.
     *
     * @ES\Property(name="name", type="keyword")
     */
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
    public ?CustomerTypeEnum $customerType = null;

    /**
     * @ES\Property(name="active", type="boolean")
     */
    private $active;

    /**
     * @return bool
     */
    public function isActive()
    {
        return $this->active;
    }

    /**
     * @param bool $active
     */
    public function setActive($active)
    {
        $this->active = $active;
    }
}
