<?php

namespace Sineflow\ElasticsearchBundle\Exception;

use Throwable;

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
     * @var bool
     */
    private $isAlias;

    /**
     * Constructor
     *
     * @param string         $indexOrAlias
     * @param bool           $isAlias
     * @param int            $code
     * @param Throwable|null $previous
     */
    public function __construct(string $indexOrAlias, bool $isAlias = false, $code = 0, Throwable $previous = null)
    {
        $this->indexOrAlias = $indexOrAlias;
        $this->isAlias = $isAlias;

        parent::__construct(sprintf('%s "%s" does not exist', $isAlias ? 'Alias' : 'Index', $indexOrAlias), $code, $previous);
    }

    /**
     * @return string
     */
    public function getIndexOrAlias(): string
    {
        return $this->indexOrAlias;
    }
}
