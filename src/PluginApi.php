<?php

declare(strict_types=1);

namespace SightPress\Plugin\Core;

/**
 * The Plugin API version is the version of sightpress/plugin-core. release-please
 * updates VERSION in every release PR (release-please-config.json, extra-files).
 */
final class PluginApi
{
    public const string VERSION = '1.2.0'; // x-release-please-version

    public const int MAJOR = 1;

    private function __construct()
    {
    }
}
