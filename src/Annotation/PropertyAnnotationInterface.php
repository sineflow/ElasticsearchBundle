<?php

namespace Sineflow\ElasticsearchBundle\Annotation;

interface PropertyAnnotationInterface
{
    public function getName(): ?string;

    public function getType(): ?string;
}
