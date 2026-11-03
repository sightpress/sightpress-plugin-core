<?php

declare(strict_types=1);

namespace SightPress\Plugin\Core\Dto;

use SightPress\Plugin\Core\Value\Locale;
use SightPress\Plugin\Core\Value\MediaId;
use SightPress\Plugin\Core\Value\SiteId;

/** A read model of a media library item in one locale. */
final readonly class MediaRef
{
    public function __construct(
        public MediaId $id,
        public SiteId $siteId,
        public string $mimeType,
        public Locale $locale,
        public ?string $alt,
        public ?string $url,
    ) {
    }
}
