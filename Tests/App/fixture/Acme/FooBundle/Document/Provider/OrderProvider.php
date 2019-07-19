<?php

namespace Sineflow\ElasticsearchBundle\Tests\App\fixture\Acme\FooBundle\Document\Provider;

use Sineflow\ElasticsearchBundle\Document\Provider\AbstractProvider;
use Sineflow\ElasticsearchBundle\Tests\App\fixture\Acme\FooBundle\Document\Order;

class OrderProvider extends AbstractProvider
{
    private $fixedDocuments = [
        1 => [
            'id' => 1,
            'order_time' => 1452250000
        ],
        2 => [
            'id' => 2,
            'order_time' => 1452251632
        ],

    ];

    public function getDocuments()
    {
        foreach ($this->fixedDocuments as $id => $data) {
            yield $this->getDocument($id);
        }
    }

    public function getDocument($id)
    {
        if (!isset($this->fixedDocuments[$id])) {
            return null;
        }

        $doc = new Order();
        $doc->id = $this->fixedDocuments[$id]['id'];
        $doc->orderTime = $this->fixedDocuments[$id]['order_time'];

        return $doc;
    }
}
