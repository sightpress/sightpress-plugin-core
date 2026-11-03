<?php

declare(strict_types=1);

namespace SightPress\Plugin\Core\ExtensionPoint;

use SightPress\Plugin\Core\Value\Locale;

/**
 * A content type. Every type uses the core Content pipeline: translations,
 * slugs and redirects, revisions, workflow, the publish gate, editors,
 * widgets and layouts. Type-specific data lives in plugin tables keyed by content id.
 */
interface ContentTypeInterface
{
    public function id(): string;

    /** Translation key of the label. */
    public function labelKey(): string;

    public function isHierarchical(): bool;

    public function isDated(): bool;

    public function inSearch(): bool;

    public function inSitemap(): bool;

    /** URL pattern for a locale, e.g. "/{locale}/articles/{slug}". */
    public function urlPattern(Locale $locale): string;

    /** Only a hint: the theme's settings decide, and an unknown layout falls back to "standard". */
    public function suggestedLayout(): ?string;
}
