<?php

namespace Sineflow\ElasticsearchBundle\Document;

use Sineflow\ElasticsearchBundle\Attribute as SFES;

/**
 * Document abstraction which introduces mandatory fields for the document.
 */
abstract class AbstractDocument implements DocumentInterface
{
    #[SFES\Id]
    public string|int|null $id = null;

    #[SFES\Score]
    public ?float $score = null;
}
