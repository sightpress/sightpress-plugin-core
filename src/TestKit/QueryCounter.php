<?php

declare(strict_types=1);

namespace SightPress\Plugin\Core\TestKit;

/** Counts database queries during a test. The core test kernel provides the real implementation. */
interface QueryCounter
{
    public function reset(): void;

    public function count(): int;
}
