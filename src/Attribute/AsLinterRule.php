<?php

declare(strict_types=1);

namespace SightPress\Plugin\Core\Attribute;

use SightPress\Plugin\Core\Dto\Severity;

/** Marks a LinterRuleInterface service. Plugins can add rules, never turn off or downgrade core ones. */
#[\Attribute(\Attribute::TARGET_CLASS)]
final readonly class AsLinterRule
{
    public function __construct(
        public string $id,
        public Severity $severity = Severity::Error,
    ) {
    }
}
