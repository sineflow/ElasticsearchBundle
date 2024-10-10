<?php

namespace Sineflow\ElasticsearchBundle\Annotation;

/**
 * Annotation used for the meta _score field, returned when searching
 *
 * @Annotation
 *
 * @Target("PROPERTY")
 */
final class Score implements PropertyAnnotationInterface
{
    public const NAME = '_score';
    public const TYPE = 'float';

    public function getName(): ?string
    {
        return self::NAME;
    }

    public function getType(): ?string
    {
        return self::TYPE;
    }
}
