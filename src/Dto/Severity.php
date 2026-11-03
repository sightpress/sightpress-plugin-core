<?php

declare(strict_types=1);

namespace SightPress\Plugin\Core\Dto;

enum Severity: string
{
    case Error = 'error';
    case Warning = 'warning';
    case Info = 'info';

    /** Only errors block publishing. */
    public function blocksPublishing(): bool
    {
        return self::Error === $this;
    }
}
