<?php

declare(strict_types=1);

namespace SightPress\Plugin\Core\Port;

use SightPress\Plugin\Core\Dto\MediaCredit;
use SightPress\Plugin\Core\Dto\MediaRef;

/**
 * Adds a file to the site's media library with exactly the checks of an
 * upload (content sniffing, limits, quota, location stripping, malware scan).
 * It can't set alt text or make a file public.
 */
interface MediaLibraryPort
{
    public function add(string $localPath, string $originalName, ?MediaCredit $credit = null): MediaRef;
}
