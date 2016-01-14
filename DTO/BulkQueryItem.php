<?php

namespace Sineflow\ElasticsearchBundle\DTO;

use Elasticsearch\Common\Exceptions\InvalidArgumentException;

/**
 * Class representing a query within a bulk request
 */
class BulkQueryItem
{

    /**
     * @var string
     */
    private $operation;

    /**
     * @var string
     */
    private $index;

    /**
     * @var string
     */
    private $type;

    /**
     * @var array
     */
    private $query;

    /**
     * @var int
     */
    private $id;

    /**
     * @var array
     */
    private $parent;

    /**
     * @param string $operation One of: index, update, delete, create.
     * @param string $index     Elasticsearch index name.
     * @param string $type      Elasticsearch type name.
     * @param array  $query     Bulk item data/params.
     */
    public function __construct($operation, $index, $type, array $query)
    {
        if (!in_array($operation, ['index', 'create', 'update', 'delete'])) {
            throw new InvalidArgumentException('Wrong bulk operation selected');
        }

        $this->operation = $operation;
        $this->index = $index;
        $this->type = $type;
        $this->id = isset($query['_id']) ? $query['_id'] : null;
        $this->parent = isset($query['_parent']) ? $query['_parent'] : null;
        unset($query['_id'], $query['_parent']);
        $this->query = $query;
    }

    /**
     * @return string
     */
    public function getIndex()
    {
        return $this->index;
    }

    /**
     * Return array of lines for bulk request
     *
     * @param string|null $forceIndex If set, that will be the index used for the output bulk request
     * @return array
     */
    public function getLines($forceIndex = null)
    {
        $result = [];

        $result[] = [
            $this->operation => array_filter(
                [
                    '_index' => $forceIndex ?: $this->index,
                    '_type' => $this->type,
                    '_id' => $this->id,
                    '_parent' => $this->parent
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
