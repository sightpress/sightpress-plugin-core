<?php

declare(strict_types=1);

namespace SightPress\Plugin\Core\Port;

use SightPress\Plugin\Core\Dto\RateLimitResult;

interface RateLimiterPort
{
    /** Consumes one token from the named limiter for this key (never an IP unless declared in describeProcessing()). */
    public function consume(string $limiter, string $key): RateLimitResult;
}
