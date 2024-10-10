<?php

namespace Sineflow\ElasticsearchBundle\Annotation;

use Sineflow\ElasticsearchBundle\Mapping\DumperInterface;

/**
 * Annotation to mark a class as an Elasticsearch document.
 *
 * @Annotation
 *
 * @Target("CLASS")
 */
final class Document implements DumperInterface
{
    public ?string $repositoryClass = null;
    public ?string $providerClass = null;

    /**
     * Settings directly passed to Elasticsearch client as-is
     */
    public array $options = [];

    /**
     * {@inheritdoc}
     */
    public function dump(array $settings = []): array
    {
        return $this->options;
    }
}
