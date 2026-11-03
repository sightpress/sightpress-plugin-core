<?php

declare(strict_types=1);

namespace SightPress\Plugin\Core\Dto;

/**
 * Credit and licence for a media file (PLAN §5.12). Once stored it is core
 * data and stays when the plugin that supplied it is disabled.
 */
final readonly class MediaCredit
{
    public function __construct(
        public string $creator,
        public string $source,
        public string $licence,
        public bool $creditRequired = true,
        public bool $editorialOnly = false,
    ) {
        if ('' === trim($creator) || '' === trim($licence)) {
            throw new \InvalidArgumentException('A media credit needs a creator and a licence.');
        }
    }
}
