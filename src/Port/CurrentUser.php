<?php

declare(strict_types=1);

namespace SightPress\Plugin\Core\Port;

use SightPress\Plugin\Core\Value\Locale;
use SightPress\Plugin\Core\Value\UserId;

interface CurrentUser
{
    public function id(): ?UserId;

    public function locale(): Locale;

    public function isGranted(string $capability): bool;
}
