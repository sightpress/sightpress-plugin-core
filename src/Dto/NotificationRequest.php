<?php

declare(strict_types=1);

namespace SightPress\Plugin\Core\Dto;

use SightPress\Plugin\Core\Value\SiteId;

/**
 * "This happened": a registered type, the ids it concerns and who it is for.
 * It never carries content bodies or personal data (PLAN §5.14).
 */
final readonly class NotificationRequest
{
    /**
     * @param array<string, string> $ids named ids the message refers to, e.g. ['content' => '01J…']
     */
    public function __construct(
        public string $type,
        public SiteId $siteId,
        public array $ids,
        public Recipients $recipients,
    ) {
        if ('' === $type) {
            throw new \InvalidArgumentException('A notification needs a type.');
        }
    }
}
