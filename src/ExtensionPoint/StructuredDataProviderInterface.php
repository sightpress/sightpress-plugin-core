<?php

declare(strict_types=1);

namespace SightPress\Plugin\Core\ExtensionPoint;

use SightPress\Plugin\Core\Dto\ContentRef;

/** JSON-LD nodes added to the core graph. They can't replace the Organization/Person node or the canonical URL. */
interface StructuredDataProviderInterface
{
    /**
     * @param ContentRef|null $content null for the site-wide graph
     *
     * @return list<array<string, mixed>>
     */
    public function nodes(?ContentRef $content): array;
}
