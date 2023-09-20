<?php

namespace App\Services;

use App\Entity\AuditLog;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpFoundation\RequestStack;
use Twig\Sandbox\SecurityPolicyInterface;


class AuditLogService
{
    private EntityManagerInterface $em;
    private SecurityPolicyInterface $security;
    private RequestStack $requestStack;

    public function __construct(EntityManagerInterface $entityManager, RequestStack $requestStack)
    {
        $this->em = $entityManager;

        $this->requestStack = $requestStack;
    }

    public function log(string $entityType, string $entityId, string $action, array $eventData): void
    {
        $request = $this->requestStack->getCurrentRequest();
        $log = new AuditLog;
        $log->setEntityType($entityType);
        $log->setEntityId($entityId);
        $log->setAction($action);
        $log->setEventData($eventData);
        $log->setRequestRoute($request->get('_route'));
        $log->setIpAddress($request->getClientIp());
        $log->setCreatedAt(new \DateTimeImmutable);
        $this->em->persist($log);
        $this->em->flush();
    }
}