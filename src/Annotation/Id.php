<?php

namespace Sineflow\ElasticsearchBundle\Annotation;

/**
 * Annotation used for the meta _id field
 *
 * @Annotation
 *
 * @Target("PROPERTY")
 */
final class Id implements PropertyAnnotationInterface
{
    public const string NAME = '_id';
    public const string TYPE = 'keyword';

    public function getName(): string
    {
        return self::NAME;
    }

    public function getType(): string
    {
        return self::TYPE;
    }
}
