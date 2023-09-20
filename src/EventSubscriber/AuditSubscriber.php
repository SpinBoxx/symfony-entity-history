<?php

namespace App\EventSubscriber;


use App\Services\AuditLogService;
use Doctrine\Bundle\DoctrineBundle\EventSubscriber\EventSubscriberInterface;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Event\LifecycleEventArgs;
use Doctrine\ORM\Event\PostPersistEventArgs;
use Doctrine\ORM\Event\PostRemoveEventArgs;
use Doctrine\ORM\Event\PostUpdateEventArgs;
use Doctrine\ORM\Event\PreRemoveEventArgs;
use Doctrine\ORM\Mapping\PostUpdate;
use Symfony\Component\Serializer\SerializerInterface;

/**
 * Class which listens on Doctrine events and writes an audit log of any entity changes made via Doctrine.
 */
class AuditSubscriber implements EventSubscriberInterface
{
    // Thanks to PHP 8's constructor property promotion and 8.1's readonly properties, we can
    // simply declare our class properties here in the constructor parameter list! 
    public function __construct(
        private readonly AuditLogService $auditLogger,
        private readonly SerializerInterface $serializer,
        private $removals = []
    ) {
    }

    // This function tells Symfony which Doctrine events we want to listen to.
    // The corresponding functions in this class will be called when these events are triggered.
    public function getSubscribedEvents(): array
    {
        return [
            'postPersist',
            'postUpdate',
            'preRemove',
            'postRemove',
        ];
    }

    public function postPersist(PostPersistEventArgs $args): void
    {
        $entity = $args->getObject();
        $entityManager = $args->getObjectManager();
        $this->log($entity, 'insert', $entityManager);
    }

    public function postUpdate(PostUpdateEventArgs $args): void
    {
        $entity = $args->getObject();
        $entityManager = $args->getObjectManager();
        $this->log($entity, 'update', $entityManager);
    }

    // We need to store the entity in a temporary array here, because the entity's ID is no longer
    // available in the postRemove event. We convert it to an array here, so we can retain the ID for 
    // our audit log.
    public function preRemove(PreRemoveEventArgs $args): void
    {
        $entity = $args->getObject();
        $this->removals[] = $this->serializer->normalize($entity);
    }

    public function postRemove(PostRemoveEventArgs $args): void
    {
        $entity = $args->getObject();
        $entityManager = $args->getObjectManager();
        $this->log($entity, 'delete', $entityManager);
    }

    // This is the function which calls the AuditLogger service, constructing
    // the call to `AuditLogger::log()` with the appropriate parameters.
    private function log($entity, string $action, EntityManagerInterface $em): void
    {
        $entityClass = get_class($entity);
        // If the class is AuditLog entity, ignore. We don't want to audit our own audit logs!
        if ($entityClass === 'App\Entity\AuditLog') {
            return;
        }
        $entityId = $entity->getId();
        $entityType = str_replace('App\Entity\\', '', $entityClass);
        // The Doctrine unit of work keeps track of all changes made to entities.
        $uow = $em->getUnitOfWork();
        if ($action === 'delete') {
            // For deletions, we get our entity from the temporary array.
            $entityData = array_pop($this->removals);
            $entityId = $entityData['id'];
        } elseif ($action === 'insert') {
            // For insertions, we convert the entity to an array.
            $entityData = $this->serializer->normalize($entity);
        } else {
            // For updates, we get the change set from Doctrine's Unit of Work manager.
            // This gives an array which contains only the fields which have
            // changed. We then just convert the numerical indexes to something
            // a bit more readable; "from" and "to" keys for the old and new values.
            $entityData = $uow->getEntityChangeSet($entity);
            // var_dump($entityData);
            // die();
            foreach ($entityData as $field => $change) {
                $entityData[$field] = [
                    'from' => $change[0],
                    'to' => $change[1],
                ];
            }
        }
        $this->auditLogger->log($entityType, $entityId, $action, $entityData);
    }
}