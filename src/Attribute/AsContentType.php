<?php

declare(strict_types=1);

namespace SightPress\Plugin\Core\Attribute;

/** Marks a ContentTypeInterface service. */
#[\Attribute(\Attribute::TARGET_CLASS)]
final readonly class AsContentType
{
    public function __construct(public string $id)
    {
    }
}
