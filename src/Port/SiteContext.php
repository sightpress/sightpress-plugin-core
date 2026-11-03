<?php

declare(strict_types=1);

namespace SightPress\Plugin\Core\Port;

use SightPress\Plugin\Core\Value\Locale;
use SightPress\Plugin\Core\Value\SiteId;

/** The request-scoped current site. */
interface SiteContext
{
    public function siteId(): SiteId;

    public function defaultLocale(): Locale;

    /** @return list<Locale> */
    public function locales(): array;
}
