<?php

declare(strict_types=1);

namespace SightPress\Plugin\Core\Dto;

use SightPress\Plugin\Core\Value\Locale;
use SightPress\Plugin\Core\Value\SiteId;
use SightPress\Plugin\Core\Value\WidgetId;

/** A read model of a widget in one locale. */
final readonly class WidgetRef
{
    public function __construct(
        public WidgetId $id,
        public SiteId $siteId,
        public string $type,
        public Locale $locale,
        public string $title,
    ) {
    }
}
