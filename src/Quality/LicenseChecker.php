<?php

declare(strict_types=1);

namespace SightPress\Plugin\Core\Quality;

/**
 * Checks that dependency licences are compatible with GPL-2.0-or-later.
 * Apache-2.0 is allowed because "or later" lets the combined work be GPL-3.0.
 */
final class LicenseChecker
{
    private const array COMPATIBLE = [
        'MIT', 'MIT-0', 'BSD-2-Clause', 'BSD-3-Clause', 'ISC', 'Apache-2.0', 'Zlib', 'Unlicense', 'CC0-1.0',
        'LGPL-2.1-only', 'LGPL-2.1-or-later', 'LGPL-3.0-only', 'LGPL-3.0-or-later',
        'GPL-2.0-or-later', 'GPL-3.0-only', 'GPL-3.0-or-later', 'MPL-2.0', 'BSL-1.0', 'PHP-3.01', 'Python-2.0', 'WTFPL',
    ];

    /** Maps legacy composer identifiers to SPDX. */
    private const array ALIASES = [
        'GPL-2.0+' => 'GPL-2.0-or-later', 'GPL-3.0+' => 'GPL-3.0-or-later', 'GPL-2.0' => 'GPL-2.0-only', 'GPL-3.0' => 'GPL-3.0-only',
        'LGPL-2.1' => 'LGPL-2.1-only', 'LGPL-3.0' => 'LGPL-3.0-only', 'LGPL-2.1+' => 'LGPL-2.1-or-later', 'LGPL-3.0+' => 'LGPL-3.0-or-later',
        'GPL-2.0-or-later' => 'GPL-2.0-or-later',
    ];

    /**
     * A package is fine when at least one of its alternative licences is compatible.
     *
     * @param list<string> $licenses
     */
    public function isCompatible(array $licenses): bool
    {
        foreach ($licenses as $license) {
            $license = self::ALIASES[$license] ?? $license;
            if (\in_array($license, self::COMPATIBLE, true)) {
                return true;
            }
        }

        return false;
    }

    /**
     * @param string $installedJson path to vendor/composer/installed.json
     *
     * @return list<string> descriptions of incompatible runtime packages
     */
    public function findProblems(string $installedJson, string $composerJson): array
    {
        $installed = json_decode((string) file_get_contents($installedJson), true, 512, \JSON_THROW_ON_ERROR);
        $root = json_decode((string) file_get_contents($composerJson), true, 512, \JSON_THROW_ON_ERROR);
        if (!\is_array($installed) || !\is_array($root)) {
            throw new \RuntimeException('Cannot read the installed packages.');
        }

        /** @var array<string, mixed> $root */
        $dev = $installed['dev-package-names'] ?? [];
        $devNames = \is_array($dev) ? array_flip(array_filter($dev, \is_string(...))) : [];
        $packages = \is_array($installed['packages'] ?? null) ? $installed['packages'] : [];

        $problems = [];
        foreach ($packages as $package) {
            if (!\is_array($package) || !\is_string($package['name'] ?? null)) {
                continue;
            }
            $name = $package['name'];
            if (isset($devNames[$name]) || str_starts_with($name, 'sightpress/')) {
                continue;
            }
            $licenses = array_values(array_filter((array) ($package['license'] ?? []), \is_string(...)));
            if (!$this->isCompatible($licenses)) {
                $problems[] = \sprintf('%s: %s', $name, [] === $licenses ? 'no licence declared' : implode(', ', $licenses));
            }
        }

        if (($root['license'] ?? null) !== 'GPL-2.0-or-later') {
            $problems[] = 'composer.json: "license" must be "GPL-2.0-or-later".';
        }

        return $problems;
    }
}
