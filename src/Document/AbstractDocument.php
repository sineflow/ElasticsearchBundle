<?php

namespace Sineflow\ElasticsearchBundle\Document;

use Sineflow\ElasticsearchBundle\Annotation as ES;

/**
 * Document abstraction which introduces mandatory fields for the document.
 */
abstract class AbstractDocument implements DocumentInterface
{
    /**
     * @ES\Id
     */
    public string|int|null $id = null;

    /**
     * @ES\Score
     */
    public ?float $score = null;
}
