<?php

declare(strict_types=1);

namespace SightPress\Plugin\Core\ExtensionPoint;

/** A member account page, in the same layout and form theme as the core account pages. */
interface AccountSectionInterface
{
    public function id(): string;

    public function labelKey(): string;

    public function route(): string;
}
