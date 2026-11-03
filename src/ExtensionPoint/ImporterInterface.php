<?php

declare(strict_types=1);

namespace SightPress\Plugin\Core\ExtensionPoint;

use SightPress\Plugin\Core\Value\SiteId;

/** An importer. Imported content goes through the same validation and linter as typed content. */
interface ImporterInterface
{
    public function id(): string;

    public function labelKey(): string;

    public function supports(string $path): bool;

    /** @return int the number of items created or updated */
    public function import(string $path, SiteId $site): int;
}
