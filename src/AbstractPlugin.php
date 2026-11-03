<?php

declare(strict_types=1);

namespace SightPress\Plugin\Core;

use SightPress\Plugin\Core\Manifest\ManifestReader;
use SightPress\Plugin\Core\Manifest\PluginManifest;
use Symfony\Component\HttpKernel\Bundle\Bundle;

/**
 * Base class of every plugin bundle. The manifest is read from the plugin's
 * composer.json, which sits one level above the bundle's src/ folder.
 */
abstract class AbstractPlugin extends Bundle
{
    private ?PluginManifest $manifest = null;

    final public function getManifest(): PluginManifest
    {
        return $this->manifest ??= new ManifestReader()->readFile($this->getPath().'/composer.json');
    }

    final public function getPluginId(): string
    {
        return $this->getManifest()->id;
    }

    /** The plugin's root folder (holding composer.json, migrations/, templates/, translations/). */
    #[\Override]
    public function getPath(): string
    {
        if (!isset($this->path)) {
            $reflected = new \ReflectionObject($this);
            $this->path = \dirname((string) $reflected->getFileName(), 2);
        }

        return $this->path;
    }
}
