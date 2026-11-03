<?php

declare(strict_types=1);

namespace SightPress\Plugin\Core\ExtensionPoint;

use SightPress\Plugin\Core\Dto\ContentRef;

/** A share target. No trackers: it only builds a plain link. */
interface ShareTargetInterface
{
    public function id(): string;

    public function labelKey(): string;

    public function shareUrl(ContentRef $content): string;
}
