<?php

namespace Sineflow\ElasticsearchBundle\Attribute;

/**
 * Attribute used for the meta _score field, returned when searching
 */
#[\Attribute(\Attribute::TARGET_PROPERTY)]
class Score implements PropertyAttributeInterface
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
