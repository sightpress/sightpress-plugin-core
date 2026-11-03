<?php

declare(strict_types=1);

namespace SightPress\Plugin\Core\Attribute;

/** Marks a BlockTypeInterface service. A block registers only if it passes the Markdown round-trip test. */
#[\Attribute(\Attribute::TARGET_CLASS)]
final readonly class AsBlockType
{
    public function __construct(public string $id)
    {
    }
}
