<?php

namespace Sineflow\ElasticsearchBundle\Event;

/**
 * Class Events
 */
final class Events
{
    /**
     * Dispatched on persisting an entity via the index manager
     */
    public const PRE_PERSIST = 'sfes.pre_persist';

    /**
     * Dispatched after a bulk request is submitted to Elasticsearch
     */
    public const POST_COMMIT = 'sfes.post_commit';
}
