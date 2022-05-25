<?php

namespace Sineflow\ElasticsearchBundle\Subscriber;

use Knp\Component\Pager\Event\ItemsEvent;
use Sineflow\ElasticsearchBundle\Finder\Adapter\KnpPaginatorAdapter;
use Sineflow\ElasticsearchBundle\Finder\Finder;
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

    public function __construct(RequestStack $requestStack)
    {
        $this->requestStack = $requestStack;
    }

    /**
     * @return array
     */
    public static function getSubscribedEvents()
    {
        return [
            'knp_pager.items' => ['items', 1],
        ];
    }

    public function items(ItemsEvent $event)
    {
        if ($event->target instanceof KnpPaginatorAdapter) {
            // Add sort to query
            [$sortField, $sortDirection] = $this->getSorting($event);
            $results = $event->target->getResults($event->getOffset(), $event->getLimit(), $sortField, $sortDirection);
            $event->count = $event->target->getTotalHits();

            $resultsType = $event->target->getResultsType();
            switch ($resultsType) {
                case Finder::RESULTS_OBJECT:
                    /* @var DocumentIterator $results */
                    $event->items = \iterator_to_array($results);
                    $event->setCustomPaginationParameter('aggregations', $results->getAggregations());
                    $event->setCustomPaginationParameter('suggestions', $results->getSuggestions());
                    break;

                case Finder::RESULTS_ARRAY:
                    $event->items = $results;
                    break;

                case Finder::RESULTS_RAW:
                    $event->items = $results['hits']['hits'];
                    $event->setCustomPaginationParameter('aggregations', $results['aggregations'] ?? []);
                    $event->setCustomPaginationParameter('suggestions', $results['suggestions'] ?? []);
                    break;

                default:
                    throw new \InvalidArgumentException(\sprintf('Unsupported results type "%s" for KNP paginator', $resultsType));
            }

            $event->stopPropagation();
        }
    }

    /**
     * Get and validate the KNP sorting params
     *
     * @return array
     */
    protected function getSorting(ItemsEvent $event)
    {
        $sortField = null;
        $sortDirection = 'asc';

        $request = $this->requestStack->getCurrentRequest();
        if ($request instanceof Request) {
            $sortField = isset($event->options['sortFieldParameterName']) ? $request->get($event->options['sortFieldParameterName']) : null;
            $sortDirection = isset($event->options['sortDirectionParameterName']) ? $request->get($event->options['sortDirectionParameterName'], 'desc') : null;
            $sortDirection = 'desc' === \strtolower($sortDirection) ? 'desc' : 'asc';

            if ($sortField) {
                // check if the requested sort field is in the sort whitelist
                if (isset($event->options['sortFieldWhitelist']) && !\in_array($sortField, $event->options['sortFieldWhitelist'])) {
                    throw new \UnexpectedValueException(\sprintf('Cannot sort by [%s] as it is not in the whitelist', $sortField));
                }
            }
        }

        return [$sortField, $sortDirection];
    }
}
