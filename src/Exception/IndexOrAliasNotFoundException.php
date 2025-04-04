<?php

namespace Sineflow\ElasticsearchBundle\Exception;

/**
 * Class IndexOrAliasNotFoundException
 */
class IndexOrAliasNotFoundException extends \RuntimeException implements ElasticsearchBundleException
{
    public function __construct(
        private readonly string $indexOrAlias,
        bool $isAlias = false,
        int $code = 0,
        ?\Throwable $previous = null,
    ) {
        parent::__construct(\sprintf('%s "%s" does not exist', $isAlias ? 'Alias' : 'Index', $this->indexOrAlias), $code, $previous);
    }

    public function getIndexOrAlias(): string
    {
        return $this->indexOrAlias;
    }
}
