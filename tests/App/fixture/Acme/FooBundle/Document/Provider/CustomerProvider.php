<?php

namespace Sineflow\ElasticsearchBundle\Tests\App\Fixture\Acme\FooBundle\Document\Provider;

use Sineflow\ElasticsearchBundle\Document\Provider\AbstractProvider;
use Sineflow\ElasticsearchBundle\Tests\App\Fixture\Acme\FooBundle\Document\Customer;

class CustomerProvider extends AbstractProvider
{
    private $fixedDocuments = [
        1 => [
            'id' => 1,
            'name' => 'John',
        ],
        2 => [
            'id' => 2,
            'name' => 'Jane',
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

        $doc = new Customer();
        $doc->id = $this->fixedDocuments[$id]['id'];
        $doc->name = $this->fixedDocuments[$id]['name'];

        return $doc;
    }
}
