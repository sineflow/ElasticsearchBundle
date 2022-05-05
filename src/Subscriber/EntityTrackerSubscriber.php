<?php

namespace Sineflow\ElasticsearchBundle\Subscriber;

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
    /**
     * @var array
     */
    private $entitiesData = [];

    /**
     * @var DocumentMetadataCollector
     */
    private $documentMetadataCollector;

    /**
     * @param DocumentMetadataCollector $documentMetadataCollector
     */
    public function __construct(DocumentMetadataCollector $documentMetadataCollector)
    {
        $this->documentMetadataCollector = $documentMetadataCollector;
    }

    /**
     * @return array
     */
    public static function getSubscribedEvents()
    {
        return [
            Events::PRE_PERSIST => 'onPrePersist',
            Events::POST_COMMIT => 'onPostCommit',
        ];
    }

    /**
     * @param PrePersistEvent $prePersistEvent
     *
     * @throws \ReflectionException
     */
    public function onPrePersist(PrePersistEvent $prePersistEvent)
    {
        // Track entity only if it has an @Id field
        $propertiesMetadata = $this->documentMetadataCollector->getObjectPropertiesMetadata(
            get_class($prePersistEvent->getDocument())
        );
        if (isset($propertiesMetadata['_id'])) {
            $bulkOperationIndex = $prePersistEvent->getBulkOperationIndex();
            $this->entitiesData[$prePersistEvent->getConnectionName()][$bulkOperationIndex]['entity'] = $prePersistEvent->getDocument();
            $this->entitiesData[$prePersistEvent->getConnectionName()][$bulkOperationIndex]['metadata'] = $propertiesMetadata;
        }
    }

    /**
     * @param PostCommitEvent $postCommitEvent
     */
    public function onPostCommit(PostCommitEvent $postCommitEvent)
    {
        // No need to do anything if there are no persisted entities for that connection
        if (empty($this->entitiesData[$postCommitEvent->getConnectionName()])) {
            return;
        }

        // Update the ids of persisted entity objects
        foreach ($this->entitiesData[$postCommitEvent->getConnectionName()] as $bulkOperationIndex => $entityData) {
            $idValue = current($postCommitEvent->getBulkResponse()['items'][$bulkOperationIndex])['_id'];
            $idPropertyMetadata = $entityData['metadata']['_id'];
            $entity = $entityData['entity'];
            if (DocumentMetadata::PROPERTY_ACCESS_PRIVATE === $idPropertyMetadata['propertyAccess']) {
                $entity->{$idPropertyMetadata['methods']['setter']}($idValue);
            } else {
                $entity->{$idPropertyMetadata['propertyName']} = $idValue;
            }
        }

        // Clear the array to avoid any memory leaks
        $this->entitiesData[$postCommitEvent->getConnectionName()] = [];
    }
}
