<?php

namespace Sineflow\ElasticsearchBundle\Subscriber;

use Knp\Component\Pager\Event\ItemsEvent;
use Sineflow\ElasticsearchBundle\Exception\Exception;
use Sineflow\ElasticsearchBundle\Finder\Finder;
use Sineflow\ElasticsearchBundle\Paginator\KnpPaginatorAdapter;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
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
                    $event->items = iterator_to_array($results);
                    break;

                case Finder::RESULTS_ARRAY:
                    $event->items = $results;
                    break;

                case Finder::RESULTS_RAW:
                    $event->items = $results['hits']['hits'];
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
     * @return array
     */
    protected function getSorting(ItemsEvent $event)
    {
        $sortField = null;
        $sortDirection = 'asc';

        $request = $this->requestStack->getCurrentRequest();

        if ($request) {
            $sortField = $request->get($event->options['sortFieldParameterName']);
            $sortDirection = $request->get($event->options['sortDirectionParameterName']);
            $sortDirection = ('desc' === strtolower($sortDirection)) ? 'desc' : 'asc';

            // check if the requested sort field is in the sort whitelist
            if (isset($event->options['sortFieldWhitelist']) && !in_array($sortField, $event->options['sortFieldWhitelist'])) {
                throw new \UnexpectedValueException(sprintf('Cannot sort by [%s] as it is not in the whitelist', $sortField));
            }
        }

        return [$sortField, $sortDirection];
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
}
