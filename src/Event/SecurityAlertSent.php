<?php

declare(strict_types=1);

namespace SightPress\Plugin\Core\Event;

use SightPress\Plugin\Core\Value\SiteId;
use SightPress\Plugin\Core\Value\UserId;

/** A core security alert was emailed. The `notifications-helper` plugin may add channels, never remove the email. */
final readonly class SecurityAlertSent extends DomainEvent
{
    public function __construct(SiteId $siteId, public UserId $userId, public string $alertType, ?\DateTimeImmutable $occurredAt = null)
    {
        parent::__construct($siteId, $occurredAt);
    }
}
