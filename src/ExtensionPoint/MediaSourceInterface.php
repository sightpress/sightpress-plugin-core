<?php

declare(strict_types=1);

namespace SightPress\Plugin\Core\ExtensionPoint;

/**
 * Adds an entry such as "Find images" to Admin → Media and the editors' "Insert
 * media" dialog. Files come in only through MediaLibraryPort.
 */
interface MediaSourceInterface
{
    public function id(): string;

    public function labelKey(): string;

    public function route(): string;

    public function capability(): string;
}
