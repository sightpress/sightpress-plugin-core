<?php

declare(strict_types=1);

namespace SightPress\Plugin\Core\ExtensionPoint;

/** Drops a plugin's data on every site. Run by `sightpress:plugin:uninstall` after an explicit confirmation. */
interface UninstallerInterface
{
    public function uninstall(): void;
}
