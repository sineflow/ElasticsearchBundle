<?php

namespace Sineflow\ElasticsearchBundle\CacheWarmer;

use Sineflow\ElasticsearchBundle\Mapping\DocumentMetadataCollector;
use Symfony\Component\HttpKernel\CacheWarmer\CacheWarmerInterface;

class MetadataCacheWarmer implements CacheWarmerInterface
{
    public function __construct(private readonly DocumentMetadataCollector $metadataCollector)
    {
    }

    public function isOptional(): bool
    {
        return true;
    }

    public function warmUp(string $cacheDir, ?string $buildDir = null): array
    {
        return $this->metadataCollector->warmUp($cacheDir, $buildDir);
    }
}
