<?php

namespace Sineflow\ElasticsearchBundle\Subscriber;

use Knp\Component\Pager\Event\ItemsEvent;
use Sineflow\ElasticsearchBundle\Finder\Finder;
use Sineflow\ElasticsearchBundle\Finder\Adapter\KnpPaginatorAdapter;
use Sineflow\ElasticsearchBundle\Result\DocumentIterator;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;

/**
 * Subscriber to paginate Elasticsearch query for KNP paginator
 */
class KnpPaginateQuerySubscriber implements EventSubscriberInterface
{
    /**
     * @var RequestStack
     */
    private $requestStack;

    /**
     * @param RequestStack $requestStack
     */
    public function __construct(RequestStack $requestStack)
    {
        $this->requestStack = $requestStack;
    }

    /**
     * @return array
     */
    public static function getSubscribedEvents()
    {
        return array(
            'knp_pager.items' => array('items', 1),
        );
    }

    /**
     * @param ItemsEvent $event
     */
    public function items(ItemsEvent $event)
    {
        if ($event->target instanceof KnpPaginatorAdapter) {
            // Add sort to query
            list($sortField, $sortDirection) = $this->getSorting($event);
            $results = $event->target->getResults($event->getOffset(), $event->getLimit(), $sortField, $sortDirection);
            $event->count = $event->target->getTotalHits();

            $resultsType = $event->target->getResultsType();
            switch ($resultsType) {
                case Finder::RESULTS_OBJECT:
                    /** @var DocumentIterator $results */
                    $event->items = iterator_to_array($results);
                    $event->setCustomPaginationParameter('aggregations', $results->getAggregations());
                    $event->setCustomPaginationParameter('suggestions', $results->getSuggestions());
                    break;

                case Finder::RESULTS_ARRAY:
                    $event->items = $results;
                    break;

                case Finder::RESULTS_RAW:
                    $event->items = $results['hits']['hits'];
                    $event->setCustomPaginationParameter('aggregations', isset($results['aggregations']) ? $results['aggregations'] : []);
                    $event->setCustomPaginationParameter('suggestions', isset($results['suggestions']) ? $results['suggestions'] : []);
                    break;

                default:
                    throw new \InvalidArgumentException(sprintf('Unsupported results type "%s" for KNP paginator', $resultsType));
            }

            $event->stopPropagation();
        }
    }

    /**
     * Get and validate the KNP sorting params
     *
     * @param ItemsEvent $event
     *
     * @return array
     */
    protected function getSorting(ItemsEvent $event)
    {
        $sortField = null;
        $sortDirection = 'asc';

        if (isset($_GET[$event->options['sortFieldParameterName']])) {
            $sortField = $_GET[$event->options['sortFieldParameterName']];
            $sortDirection = isset($_GET[$event->options['sortDirectionParameterName']]) && strtolower($_GET[$event->options['sortDirectionParameterName']]) === 'desc' ? 'desc' : 'asc';

            // check if the requested sort field is in the sort whitelist
            if (isset($event->options['sortFieldWhitelist']) && !in_array($sortField, $event->options['sortFieldWhitelist'])) {
                throw new \UnexpectedValueException(sprintf('Cannot sort by [%s] as it is not in the whitelist', $sortField));
            }
        }
        
        return [$sortField, $sortDirection];
    }
}
