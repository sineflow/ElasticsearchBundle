<?php

namespace Sineflow\ElasticsearchBundle\Tests\App\Fixture\Acme\FooBundle\Document;

use Sineflow\ElasticsearchBundle\Annotation as ES;
use Sineflow\ElasticsearchBundle\Document\AbstractDocument;
use Sineflow\ElasticsearchBundle\Tests\App\Fixture\Acme\FooBundle\Document\Provider\CustomerProvider;

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
     * @var string
     *
     * @ES\Property(name="name", type="keyword")
     */
    public $name;

    /**
     * @var bool
     *
     * @ES\Property(name="active", type="boolean")
     */
    private $active;

    /**
     * @return boolean
     */
    public function isActive()
    {
        return $this->active;
    }

    /**
     * @param boolean $active
     */
    public function setActive($active)
    {
        $this->active = $active;
    }
}
