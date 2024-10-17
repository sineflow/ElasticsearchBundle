<?php

namespace Sineflow\ElasticsearchBundle\Subscriber;

use Psr\Cache\InvalidArgumentException;
use Sineflow\ElasticsearchBundle\Event\Events;
use Sineflow\ElasticsearchBundle\Event\PostCommitEvent;
use Sineflow\ElasticsearchBundle\Event\PrePersistEvent;
use Sineflow\ElasticsearchBundle\Mapping\DocumentMetadata;
use Sineflow\ElasticsearchBundle\Mapping\DocumentMetadataCollector;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

/**
 * Class EntityTrackerSubscriber
 *
 * Tracks persisted entities and updates their ids after they have been inserted in Elasticsearch via the bulk request
 */
class EntityTrackerSubscriber implements EventSubscriberInterface
{
    private array $entitiesData = [];

    public function __construct(private readonly DocumentMetadataCollector $documentMetadataCollector)
    {
    }

    public static function getSubscribedEvents(): array
    {
        return [
            Events::PRE_PERSIST => 'onPrePersist',
            Events::POST_COMMIT => 'onPostCommit',
        ];
    }

    /**
     * @throws InvalidArgumentException
     */
    public function onPrePersist(PrePersistEvent $prePersistEvent): void
    {
        // Track entity only if it has an @Id field
        $propertiesMetadata = $this->documentMetadataCollector->getObjectPropertiesMetadata(
            $prePersistEvent->getDocument()::class
        );
        if (isset($propertiesMetadata['_id'])) {
            $bulkOperationIndex = $prePersistEvent->getBulkOperationIndex();
            $this->entitiesData[$prePersistEvent->getConnectionName()][$bulkOperationIndex]['entity'] = $prePersistEvent->getDocument();
            $this->entitiesData[$prePersistEvent->getConnectionName()][$bulkOperationIndex]['metadata'] = $propertiesMetadata;
        }
    }

    public function onPostCommit(PostCommitEvent $postCommitEvent): void
    {
        // No need to do anything if there are no persisted entities for that connection
        if (empty($this->entitiesData[$postCommitEvent->getConnectionName()])) {
            return;
        }

        // Update the ids of persisted entity objects
        foreach ($this->entitiesData[$postCommitEvent->getConnectionName()] as $bulkOperationIndex => $entityData) {
            $bulkResponseItem = $postCommitEvent->getBulkResponse()['items'][$bulkOperationIndex];
            $operation = \key($bulkResponseItem);
            $bulkResponseItemValue = \current($bulkResponseItem);
            if (in_array($operation, ['create', 'index']) && !isset($bulkResponseItemValue['error'])) {
                $idValue = $bulkResponseItemValue['_id'];
                $idPropertyMetadata = $entityData['metadata']['_id'];
                $entity = $entityData['entity'];
                if (DocumentMetadata::PROPERTY_ACCESS_PRIVATE === $idPropertyMetadata['propertyAccess']) {
                    $entity->{$idPropertyMetadata['methods']['setter']}($idValue);
                } else {
                    $entity->{$idPropertyMetadata['propertyName']} = $idValue;
                }
            }
        }

        // Clear the array to avoid any memory leaks
        $this->entitiesData[$postCommitEvent->getConnectionName()] = [];
    }
}
