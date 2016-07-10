<?php

namespace Sineflow\ElasticsearchBundle\Document;

use Sineflow\ElasticsearchBundle\Annotation as ES;

/**
 * Document abstraction which introduces mandatory fields for the document.
 */
abstract class AbstractDocument implements DocumentInterface
{
    /**
     * @var string
     *
     * @ES\Id
     */
    public $id;

    /**
     * @var float
     *
     * @ES\Score
     */
    public $score;
}
