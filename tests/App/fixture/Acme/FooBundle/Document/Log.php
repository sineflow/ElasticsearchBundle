<?php

namespace Sineflow\ElasticsearchBundle\Tests\App\Fixture\Acme\FooBundle\Document;

use Sineflow\ElasticsearchBundle\Annotation as ES;
use Sineflow\ElasticsearchBundle\Document\AbstractDocument;

/**
 * @ES\Document;
 */
class Log extends AbstractDocument
{
    /**
     * @var string
     *
     * @ES\Property(name="entry", type="keyword")
     */
    public $entry;
}
