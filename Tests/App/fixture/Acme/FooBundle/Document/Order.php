<?php

namespace Sineflow\ElasticsearchBundle\Tests\App\fixture\Acme\FooBundle\Document;

use Sineflow\ElasticsearchBundle\Annotation as ES;
use Sineflow\ElasticsearchBundle\Document\AbstractDocument;

/**
 * @ES\Document(type="order");
 */
class Order extends AbstractDocument
{
    /**
     * @var int
     *
     * @ES\Property(name="order_time", type="integer")
     */
    public $orderTime;
}
