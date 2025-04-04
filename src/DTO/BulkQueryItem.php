<?php

namespace Sineflow\ElasticsearchBundle\DTO;

use Sineflow\ElasticsearchBundle\Exception\InvalidArgumentException;

/**
 * Class representing a query within a bulk request
 */
class BulkQueryItem
{
    private readonly array $query;
    private readonly array $metaParams;

    /**
     * @param string $operation  One of: index, update, delete, create.
     * @param string $index      Elasticsearch index name.
     * @param array  $query      Bulk item query (aka optional_source in the ES docs)
     * @param array  $metaParams Additional params to pass with the metadata in the bulk request (_version, _routing, etc.)
     */
    public function __construct(
        private readonly string $operation,
        private readonly string $index,
        array $query,
        array $metaParams = [],
    ) {
        if (!\in_array($this->operation, ['index', 'create', 'update', 'delete'])) {
            throw new InvalidArgumentException(\sprintf('Invalid bulk operation "%s" specified', $operation));
        }

        // in case some meta param is specified as part of the query and not in $metaParams, move it there
        // (this happens when converting a document entity to an array)
        foreach (['_id'] as $metaParam) {
            if (isset($query[$metaParam])) {
                $metaParams[$metaParam] = $query[$metaParam];
                unset($query[$metaParam]);
            }
        }

        $this->query = $query;
        $this->metaParams = $metaParams;
    }

    public function getIndex(): string
    {
        return $this->index;
    }

    public function getQuery(): array
    {
        return $this->query;
    }

    /**
     * Return array of lines for bulk request
     *
     * @param string|null $forceIndex If set, that will be the index used for the output bulk request
     */
    public function getLines(?string $forceIndex = null): array
    {
        $result = [];

        $result[] = [
            $this->operation => \array_merge(
                $this->metaParams,
                [
                    '_index' => $forceIndex ?: $this->index,
                ]
            ),
        ];

        switch ($this->operation) {
            case 'index':
            case 'create':
            case 'update':
                $result[] = $this->query;
                break;
            case 'delete':
                // Body for delete operation is not needed to apply.
            default:
                // Do nothing.
                break;
        }

        return $result;
    }
}
