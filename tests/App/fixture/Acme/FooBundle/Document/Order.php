<?php

namespace Sineflow\ElasticsearchBundle\Tests\App\Fixture\Acme\FooBundle\Document;

use Sineflow\ElasticsearchBundle\Attribute as SFES;
use Sineflow\ElasticsearchBundle\Document\AbstractDocument;
use Sineflow\ElasticsearchBundle\Tests\App\Fixture\Acme\FooBundle\Document\Provider\OrderProvider;

#[SFES\Document(
    providerClass: OrderProvider::class,
)]
class Order extends AbstractDocument
{
    #[SFES\Property(
        name: 'order_time',
        type: 'integer',
    )]
    public ?int $orderTime = null;
}
