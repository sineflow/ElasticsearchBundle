<?php

namespace Sineflow\ElasticsearchBundle\Exception;

/**
 * Class IndexOrAliasNotFoundException
 */
class IndexOrAliasNotFoundException extends Exception
{
    /**
     * @var string
     */
    private $indexOrAlias;

    /**
     * Constructor
     *
     * @param int $code
     */
    public function __construct(string $indexOrAlias, bool $isAlias = false, $code = 0, ?\Throwable $previous = null)
    {
        $this->indexOrAlias = $indexOrAlias;

        parent::__construct(\sprintf('%s "%s" does not exist', $isAlias ? 'Alias' : 'Index', $indexOrAlias), $code, $previous);
    }

    public function getIndexOrAlias(): string
    {
        return $this->indexOrAlias;
    }
}
