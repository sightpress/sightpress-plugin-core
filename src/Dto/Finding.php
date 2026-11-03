<?php

declare(strict_types=1);

namespace SightPress\Plugin\Core\Dto;

/**
 * One linter result. The message is shown to people in words, so it must be
 * already translated and say how to fix the problem.
 */
final readonly class Finding
{
    public function __construct(
        public string $ruleId,
        public Severity $severity,
        public string $message,
        public ?string $location = null,
    ) {
        if ('' === $ruleId || '' === $message) {
            throw new \InvalidArgumentException('A finding needs a rule id and a message.');
        }
    }
}
