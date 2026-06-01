<?php

namespace App\Modules\IntegrationEvent\Service;

use App\Modules\Connector\Domain\Connector;
use App\Modules\IntegrationEvent\Domain\IntegrationEvent;
use App\Modules\IntegrationEvent\Repository\IntegrationEventRepository;
use App\Shared\Exceptions\BusinessConflictException;

class IntegrationEventService
{
    public function __construct(
        private readonly IntegrationEventRepository $integrationEventRepository
    ) {}

    public function createEvent(Connector $connector, string $externalEventId, string $eventType, array $payload): IntegrationEvent {

        if(!$connector->canSendEvent($eventType)) throw new BusinessConflictException("Connector is not authorized to send this event type");
        
        $event = new IntegrationEvent();

        $event->setConnectorId($connector->getId());
        $event->setOrganizationId($connector->getOrganizationId());
        $event->setExternalEventId($externalEventId);
        $event->setEventType(trim($eventType));
        $event->setPayload($payload);
        $event->setStatus('pending');

        return $this->integrationEventRepository->saveReturn($event);
    }
}