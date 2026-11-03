<?php

declare(strict_types=1);

namespace SightPress\Plugin\Core\Event;

use SightPress\Plugin\Core\Value\SiteId;
use SightPress\Plugin\Core\Value\UserId;

final readonly class MemberDeleted extends DomainEvent
{
    public function __construct(SiteId $siteId, public UserId $userId, ?\DateTimeImmutable $occurredAt = null)
    {
        parent::__construct($siteId, $occurredAt);
    }
}
