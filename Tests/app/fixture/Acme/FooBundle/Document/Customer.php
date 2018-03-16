<?php

namespace Sineflow\ElasticsearchBundle\Tests\app\fixture\Acme\FooBundle\Document;

use Sineflow\ElasticsearchBundle\Annotation as ES;
use Sineflow\ElasticsearchBundle\Document\AbstractDocument;

/**
 * @ES\Document(type="customer");
 */
class Customer extends AbstractDocument
{
    /**
     * Test adding raw mapping.
     *
     * @var string
     *
     * @ES\Property(name="name", type="keyword", options={"index"="not_analyzed"})
     */
    public $name;

    /**
     * @var boolean
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
