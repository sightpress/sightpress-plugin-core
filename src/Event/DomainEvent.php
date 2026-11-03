<?php

declare(strict_types=1);

namespace SightPress\Plugin\Core\Event;

use SightPress\Plugin\Core\Value\SiteId;

/**
 * Base of the domain events plugins may react to. Heavy work goes to async jobs.
 */
abstract readonly class DomainEvent
{
    public \DateTimeImmutable $occurredAt;

    public function __construct(public SiteId $siteId, ?\DateTimeImmutable $occurredAt = null)
    {
        $this->occurredAt = $occurredAt ?? new \DateTimeImmutable();
    }
}
