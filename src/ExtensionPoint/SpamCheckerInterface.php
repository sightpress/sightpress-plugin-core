<?php

declare(strict_types=1);

namespace SightPress\Plugin\Core\ExtensionPoint;

/** A spam checker. Never a CAPTCHA or a cognitive test. */
interface SpamCheckerInterface
{
    public function id(): string;

    /** @return float 0.0 (clearly fine) to 1.0 (clearly spam) */
    public function score(string $text, string $locale): float;
}
