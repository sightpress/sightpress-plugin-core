<?php

declare(strict_types=1);

namespace SightPress\Plugin\Core\Event;

use SightPress\Plugin\Core\Value\ContentId;
use SightPress\Plugin\Core\Value\Locale;
use SightPress\Plugin\Core\Value\SiteId;

final readonly class ContentPublished extends DomainEvent
{
    public function __construct(SiteId $siteId, public ContentId $contentId, public Locale $locale, public string $type, ?\DateTimeImmutable $occurredAt = null)
    {
        parent::__construct($siteId, $occurredAt);
    }
}
