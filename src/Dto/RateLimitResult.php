<?php

declare(strict_types=1);

namespace SightPress\Plugin\Core\Dto;

/** The outcome of asking a rate limiter for permission. */
final readonly class RateLimitResult
{
    public function __construct(
        public bool $accepted,
        public int $remaining,
        public ?\DateTimeImmutable $retryAfter = null,
    ) {
    }
}
