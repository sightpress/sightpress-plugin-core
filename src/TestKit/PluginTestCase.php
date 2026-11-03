<?php

declare(strict_types=1);

namespace SightPress\Plugin\Core\TestKit;

use PHPUnit\Framework\TestCase;
use SightPress\Plugin\Core\Manifest\ManifestReader;
use SightPress\Plugin\Core\Manifest\PluginManifest;
use SightPress\Plugin\Core\Quality\LicenseChecker;
use SightPress\Plugin\Core\Quality\XliffChecker;

/**
 * Base class of every plugin's test suite. Extend it, implement
 * pluginRoot(), and the contract checks below run for the plugin.
 *
 * The core-dependent checks (site isolation against a real database,
 * plugin-disabled routes, query counts) are added to this class by the core
 * test kernel as the engine lands (M6); the helpers they need are here.
 */
abstract class PluginTestCase extends TestCase
{
    use QueryCountAssertions;
    use SiteIsolationAssertions;

    /** Absolute path of the plugin's root folder (the one holding composer.json). */
    abstract protected function pluginRoot(): string;

    final protected function manifest(): PluginManifest
    {
        return new ManifestReader()->readFile($this->pluginRoot().'/composer.json');
    }

    final public function testManifestIsValid(): void
    {
        $manifest = $this->manifest();

        self::assertSame('GPL-2.0-or-later', $manifest->license);
        self::assertMatchesRegularExpression('/^\^?\d+\.\d+/', $manifest->apiConstraint);
        self::assertTrue(class_exists($manifest->bundleClass), \sprintf('Bundle class %s must exist.', $manifest->bundleClass));
        if (!$manifest->isThirdParty()) {
            self::assertSame('sightpress/plugin-'.$manifest->id, $manifest->package, 'Official plugins are named sightpress/plugin-<id>.');
        }
    }

    final public function testApiConstraintMatchesPluginCoreRequirement(): void
    {
        $composer = json_decode((string) file_get_contents($this->pluginRoot().'/composer.json'), true, 512, \JSON_THROW_ON_ERROR);
        self::assertIsArray($composer);
        $requires = $composer['require'] ?? [];
        self::assertIsArray($requires);

        self::assertSame($this->manifest()->apiConstraint, $requires['sightpress/plugin-core'] ?? null, 'extra.sightpress.api must match the require on sightpress/plugin-core.');
    }

    final public function testPluginNeverUsesCoreClasses(): void
    {
        $offences = [];
        foreach ($this->phpFiles($this->pluginRoot().'/src') as $file) {
            if (str_contains((string) file_get_contents($file), 'SightPress\\Core\\')) {
                $offences[] = $file;
            }
        }

        self::assertSame([], $offences, 'Plugins use only SightPress\\Plugin\\Core\\ and the Contract\\ namespace of plugins they require.');
    }

    final public function testTranslationsCoverTheLaunchLocales(): void
    {
        $directory = $this->pluginRoot().'/translations';
        if (!is_dir($directory)) {
            self::markTestSkipped('The plugin has no translations folder yet.');
        }

        $report = new XliffChecker()->check($directory, $this->manifest()->slug());

        self::assertSame([], $report->problems());
    }

    final public function testRuntimeDependenciesAreGplCompatible(): void
    {
        $installed = $this->pluginRoot().'/vendor/composer/installed.json';
        if (!is_file($installed)) {
            self::markTestSkipped('Run composer install first.');
        }

        self::assertSame([], new LicenseChecker()->findProblems($installed, $this->pluginRoot().'/composer.json'));
    }

    /** @return iterable<string> */
    private function phpFiles(string $directory): iterable
    {
        if (!is_dir($directory)) {
            return;
        }
        foreach (new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($directory, \FilesystemIterator::SKIP_DOTS)) as $file) {
            if ($file instanceof \SplFileInfo && 'php' === $file->getExtension()) {
                yield $file->getPathname();
            }
        }
    }
}
