<?php

declare(strict_types=1);

namespace SightPress\Plugin\Core\Event;

use SightPress\Plugin\Core\Value\MediaId;
use SightPress\Plugin\Core\Value\SiteId;

final readonly class MediaDeleted extends DomainEvent
{
    public function __construct(SiteId $siteId, public MediaId $mediaId, ?\DateTimeImmutable $occurredAt = null)
    {
        parent::__construct($siteId, $occurredAt);
    }
}
