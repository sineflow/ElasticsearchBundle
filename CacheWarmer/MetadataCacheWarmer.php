<?php

namespace Sineflow\ElasticsearchBundle\CacheWarmer;

use Sineflow\ElasticsearchBundle\Mapping\DocumentMetadataCollector;
use Symfony\Component\HttpKernel\CacheWarmer\CacheWarmerInterface;
use Symfony\Component\HttpKernel\CacheWarmer\WarmableInterface;

/**
 * Class MetadataCacheWarmer
 */
class MetadataCacheWarmer implements CacheWarmerInterface
{
    /**
     * @var DocumentMetadataCollector
     */
    private $metadataCollector;

    /**
     * @param DocumentMetadataCollector $metadataCollector
     */
    public function __construct(DocumentMetadataCollector $metadataCollector)
    {
        $this->metadataCollector = $metadataCollector;
    }

    /**
     * @return bool
     */
    public function isOptional()
    {
        return true;
    }

    /**
     * @param string $cacheDir
     *
     * @return array
     *
     * @throws \Psr\Cache\InvalidArgumentException
     */
    public function warmUp($cacheDir)
    {
        if ($this->metadataCollector instanceof WarmableInterface) {
            return (array) $this->metadataCollector->warmUp($cacheDir);
        }

        throw new \LogicException(sprintf('The metadata collector "%s" cannot be warmed up because it does not implement "%s".', get_debug_type($this->metadataCollector), WarmableInterface::class));
    }
}
