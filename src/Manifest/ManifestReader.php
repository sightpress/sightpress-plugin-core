<?php

declare(strict_types=1);

namespace SightPress\Plugin\Core\Manifest;

use SightPress\Plugin\Core\Value\Locale;

/**
 * Reads and validates a plugin manifest from composer.json data.
 */
final class ManifestReader
{
    public const string PACKAGE_TYPE = 'sightpress-plugin';

    public function readFile(string $composerJsonPath): PluginManifest
    {
        $json = @file_get_contents($composerJsonPath);
        if (false === $json) {
            throw new ManifestException(\sprintf('Cannot read "%s".', $composerJsonPath));
        }

        try {
            $data = json_decode($json, true, 512, \JSON_THROW_ON_ERROR);
        } catch (\JsonException $e) {
            throw new ManifestException(\sprintf('"%s" is not valid JSON: %s', $composerJsonPath, $e->getMessage()), 0, $e);
        }

        if (!\is_array($data)) {
            throw new ManifestException(\sprintf('"%s" must hold a JSON object.', $composerJsonPath));
        }

        /** @var array<string, mixed> $data */
        return $this->read($data);
    }

    /**
     * @param array<string, mixed> $composer decoded composer.json
     */
    public function read(array $composer): PluginManifest
    {
        $package = $this->string($composer, 'name', 'composer.json');

        if (self::PACKAGE_TYPE !== ($composer['type'] ?? null)) {
            throw new ManifestException(\sprintf('Package "%s" must have type "%s".', $package, self::PACKAGE_TYPE));
        }

        $extra = $composer['extra'] ?? null;
        $section = \is_array($extra) ? ($extra['sightpress'] ?? null) : null;
        if (!\is_array($section)) {
            throw new ManifestException(\sprintf('Package "%s" has no "extra.sightpress" manifest.', $package));
        }

        /** @var array<string, mixed> $section */
        $locales = $this->stringList($section, 'locales');
        foreach ($locales as $locale) {
            try {
                Locale::fromString($locale);
            } catch (\InvalidArgumentException $e) {
                throw new ManifestException(\sprintf('Manifest of "%s": %s', $package, $e->getMessage()), 0, $e);
            }
        }

        $capabilities = [];
        $rawCapabilities = $section['capabilities'] ?? [];
        if (!\is_array($rawCapabilities)) {
            throw new ManifestException(\sprintf('Manifest of "%s": "capabilities" must be an object.', $package));
        }
        foreach ($rawCapabilities as $capability => $roles) {
            if (!\is_string($capability) || !\is_array($roles) || !array_is_list($roles) || [] !== array_filter($roles, static fn (mixed $r): bool => !\is_string($r))) {
                throw new ManifestException(\sprintf('Manifest of "%s": capability roles must be lists of strings.', $package));
            }
            /** @var list<string> $roles */
            $capabilities[$capability] = $roles;
        }

        $description = $composer['description'] ?? '';
        $license = $composer['license'] ?? '';

        return new PluginManifest(
            id: $this->string($section, 'id', 'extra.sightpress'),
            package: $package,
            bundleClass: $this->string($section, 'bundle', 'extra.sightpress'),
            apiConstraint: $this->string($section, 'api', 'extra.sightpress'),
            license: \is_string($license) ? $license : '',
            description: \is_string($description) ? $description : '',
            requires: $this->stringList($section, 'requires'),
            capabilities: $capabilities,
            routes: $this->stringList($section, 'routes'),
            adminRoutes: $this->stringList($section, 'admin_routes'),
            locales: $locales,
            outboundHosts: $this->stringList($section, 'outbound_hosts'),
        );
    }

    /**
     * @param array<string, mixed> $data
     */
    private function string(array $data, string $key, string $where): string
    {
        $value = $data[$key] ?? null;
        if (!\is_string($value) || '' === $value) {
            throw new ManifestException(\sprintf('"%s" must have a non-empty string "%s".', $where, $key));
        }

        return $value;
    }

    /**
     * @param array<string, mixed> $data
     *
     * @return list<string>
     */
    private function stringList(array $data, string $key): array
    {
        $value = $data[$key] ?? [];
        if (!\is_array($value) || !array_is_list($value)) {
            throw new ManifestException(\sprintf('Manifest key "%s" must be a list of strings.', $key));
        }
        foreach ($value as $item) {
            if (!\is_string($item)) {
                throw new ManifestException(\sprintf('Manifest key "%s" must be a list of strings.', $key));
            }
        }

        /** @var list<string> $value */
        return $value;
    }
}
