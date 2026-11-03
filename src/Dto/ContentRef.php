<?php

declare(strict_types=1);

namespace SightPress\Plugin\Core\Dto;

use SightPress\Plugin\Core\Value\ContentId;
use SightPress\Plugin\Core\Value\Locale;
use SightPress\Plugin\Core\Value\SiteId;

/** A read model of a content item in one locale. Plugins never see core entities. */
final readonly class ContentRef
{
    public function __construct(
        public ContentId $id,
        public SiteId $siteId,
        public string $type,
        public Locale $locale,
        public string $title,
        public string $url,
        public bool $published,
    ) {
    }
}
