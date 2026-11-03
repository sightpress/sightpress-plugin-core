<?php

declare(strict_types=1);

namespace SightPress\Plugin\Core\Attribute;

/** Marks a PublishGuardInterface service. Guards can only add reasons to block publishing. */
#[\Attribute(\Attribute::TARGET_CLASS)]
final readonly class AsPublishGuard
{
    public function __construct(public string $id)
    {
    }
}
