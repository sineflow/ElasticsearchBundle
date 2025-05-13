<?php

namespace Sineflow\ElasticsearchBundle\Attribute;

interface PropertyAttributeInterface
{
    public function getName(): ?string;

    public function getType(): ?string;
}
