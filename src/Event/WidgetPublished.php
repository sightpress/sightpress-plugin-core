<?php

declare(strict_types=1);

namespace SightPress\Plugin\Core\Event;

use SightPress\Plugin\Core\Value\SiteId;
use SightPress\Plugin\Core\Value\WidgetId;

final readonly class WidgetPublished extends DomainEvent
{
    public function __construct(SiteId $siteId, public WidgetId $widgetId, ?\DateTimeImmutable $occurredAt = null)
    {
        parent::__construct($siteId, $occurredAt);
    }
}
