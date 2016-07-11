<?php

namespace Sineflow\ElasticsearchBundle\Tests\app\fixture\Acme\FooBundle\Document;

use Sineflow\ElasticsearchBundle\Annotation as ES;
use Sineflow\ElasticsearchBundle\Document\AbstractDocument;

/**
 * @ES\Document(
 *     type="answers",
 *     parent="AcmeFooBundle:Question"
 * );
 */
class Answer extends AbstractDocument
{
    /**
     * @var string
     *
     * @ES\ParentId
     */
    public $parentId;

    /**
     * @var string
     *
     * @ES\Property(name="text", type="string")
     */
    public $text;
}
