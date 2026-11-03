<?php

declare(strict_types=1);

namespace SightPress\Plugin\Core\Attribute;

/** Marks an AdminSectionInterface service. */
#[\Attribute(\Attribute::TARGET_CLASS)]
final readonly class AsAdminSection
{
    public function __construct(public string $id)
    {
    }
}
