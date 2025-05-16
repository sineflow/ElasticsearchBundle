<?php

namespace Sineflow\ElasticsearchBundle\Document;

use Sineflow\ElasticsearchBundle\Annotation as ES;
use Sineflow\ElasticsearchBundle\Attribute as SFES;

/**
 * Document abstraction which introduces mandatory fields for the document.
 */
abstract class AbstractDocument implements DocumentInterface
{
    /**
     * @ES\Id
     */
    #[SFES\Id]
    public string|int|null $id = null;

    /**
     * @ES\Score
     */
    #[SFES\Score]
    public ?float $score = null;
}
