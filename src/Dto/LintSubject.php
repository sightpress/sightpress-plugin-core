<?php

declare(strict_types=1);

namespace SightPress\Plugin\Core\Dto;

use SightPress\Plugin\Core\Value\ContentId;
use SightPress\Plugin\Core\Value\Locale;
use SightPress\Plugin\Core\Value\SiteId;

/**
 * What a linter rule or publish guard looks at: one translation of one content item.
 */
final readonly class LintSubject
{
    public function __construct(
        public SiteId $siteId,
        public ContentId $contentId,
        public string $type,
        public Locale $locale,
        public string $title,
        public string $markdown,
        public string $bodyHtml,
    ) {
    }
}
