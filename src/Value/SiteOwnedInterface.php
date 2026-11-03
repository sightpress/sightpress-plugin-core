<?php

declare(strict_types=1);

namespace SightPress\Plugin\Core\Value;

/**
 * An entity that belongs to one site. The core's SiteFilter restricts every
 * query on these entities to the current site.
 */
interface SiteOwnedInterface
{
    public function getSiteId(): SiteId;
}
