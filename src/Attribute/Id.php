<?php

namespace Sineflow\ElasticsearchBundle\Attribute;

/**
 * Attribute used for the meta _id field
 */
#[\Attribute(\Attribute::TARGET_PROPERTY)]
class Id implements PropertyAttributeInterface
{
    public const NAME = '_id';
    public const TYPE = 'keyword';

    public function getName(): ?string
    {
        return self::NAME;
    }

    public function getType(): ?string
    {
        return self::TYPE;
    }
}
