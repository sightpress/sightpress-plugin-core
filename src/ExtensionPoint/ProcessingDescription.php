<?php

declare(strict_types=1);

namespace SightPress\Plugin\Core\ExtensionPoint;

/** What a provider or plugin does with personal data, in words (GDPR; PLAN §3.5). */
final readonly class ProcessingDescription
{
    public function __construct(
        public string $what,
        public string $why,
        public string $legalBasis,
        public string $retention,
    ) {
    }
}
