<?php

namespace Sineflow\ElasticsearchBundle\Attribute;

use Sineflow\ElasticsearchBundle\Mapping\DumperInterface;

#[\Attribute(\Attribute::TARGET_CLASS)]
final class Document implements DumperInterface
{
    /**
     * @param array|null $options Settings directly passed to the Elasticsearch client as-is
     */
    public function __construct(
        public ?string $repositoryClass = null,
        public ?string $providerClass = null,
        public ?array $options = [],
    ) {
    }

    public function dump(array $settings = []): array
    {
        return $this->options;
    }
}
