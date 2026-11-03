<?php

declare(strict_types=1);

namespace SightPress\Plugin\Core\Manifest;

use SightPress\Plugin\Core\Value\Locale;

/**
 * A plugin's manifest: the `extra.sightpress` section of its composer.json.
 */
final readonly class PluginManifest
{
    /**
     * @param list<string>                $requires      ids of plugins this one needs
     * @param array<string, list<string>> $capabilities  capability => default roles
     * @param list<string>                $routes        public route patterns
     * @param list<string>                $adminRoutes   admin route patterns
     * @param list<string>                $locales       BCP 47 codes the plugin ships
     * @param list<string>                $outboundHosts external hosts the plugin may call
     */
    public function __construct(
        public string $id,
        public string $package,
        public string $bundleClass,
        public string $apiConstraint,
        public string $license,
        public string $description,
        public array $requires = [],
        public array $capabilities = [],
        public array $routes = [],
        public array $adminRoutes = [],
        public array $locales = [],
        public array $outboundHosts = [],
    ) {
        if (1 !== preg_match('/^(?:[a-z0-9]+(?:-[a-z0-9]+)*\.)?[a-z0-9]+(?:-[a-z0-9]+)*$/', $id)) {
            throw new ManifestException(\sprintf('Plugin id "%s" is not valid. Use kebab-case, with an optional "vendor." prefix.', $id));
        }
    }

    /** Official plugins use bare ids; third-party ids carry a vendor prefix. */
    public function isThirdParty(): bool
    {
        return str_contains($this->id, '.');
    }

    public function vendor(): ?string
    {
        return $this->isThirdParty() ? explode('.', $this->id, 2)[0] : null;
    }

    /** The lowercase, underscore form used for service ids, tables and translation domains. */
    public function slug(): string
    {
        return str_replace(['.', '-'], '_', $this->id);
    }

    /** @return list<Locale> */
    public function localeObjects(): array
    {
        return array_map(Locale::fromString(...), $this->locales);
    }

    public function declaresCapability(string $capability): bool
    {
        return isset($this->capabilities[$capability]);
    }
}
