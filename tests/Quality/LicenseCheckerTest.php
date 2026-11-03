<?php

declare(strict_types=1);

namespace SightPress\Plugin\Core\Tests\Quality;

use PhpCsFixer\Finder;
use PHPUnit\Framework\TestCase;
use SightPress\Plugin\Core\Quality\CsFixerRules;
use SightPress\Plugin\Core\Quality\LicenseChecker;

final class LicenseCheckerTest extends TestCase
{
    public function testCompatibleLicences(): void
    {
        $checker = new LicenseChecker();

        foreach ([['MIT'], ['BSD-3-Clause'], ['Apache-2.0'], ['GPL-2.0-or-later'], ['GPL-2.0+'], ['LGPL-3.0-or-later'], ['GPL-2.0-only', 'MIT']] as $licenses) {
            self::assertTrue($checker->isCompatible($licenses), implode(',', $licenses));
        }
    }

    public function testIncompatibleLicences(): void
    {
        $checker = new LicenseChecker();

        foreach ([[], ['proprietary'], ['GPL-2.0-only'], ['AGPL-3.0-or-later'], ['CC-BY-NC-4.0']] as $licenses) {
            self::assertFalse($checker->isCompatible($licenses), implode(',', $licenses));
        }
    }

    public function testFindsProblemsInInstalledPackagesAndIgnoresDevOnes(): void
    {
        $dir = sys_get_temp_dir().'/sp-lic-'.bin2hex(random_bytes(4));
        mkdir($dir);
        file_put_contents($dir.'/installed.json', json_encode([
            'packages' => [
                ['name' => 'a/ok', 'license' => ['MIT']],
                ['name' => 'a/bad', 'license' => ['proprietary']],
                ['name' => 'a/none'],
                ['name' => 'a/dev-only', 'license' => ['proprietary']],
                ['name' => 'sightpress/core', 'license' => ['GPL-2.0-or-later']],
            ],
            'dev-package-names' => ['a/dev-only'],
        ], \JSON_THROW_ON_ERROR));
        file_put_contents($dir.'/composer.json', '{"license": "GPL-2.0-or-later"}');

        try {
            $problems = new LicenseChecker()->findProblems($dir.'/installed.json', $dir.'/composer.json');
        } finally {
            unlink($dir.'/installed.json');
            unlink($dir.'/composer.json');
            rmdir($dir);
        }

        self::assertSame(['a/bad: proprietary', 'a/none: no licence declared'], $problems);
    }

    public function testRootLicenceMustBeGpl(): void
    {
        $dir = sys_get_temp_dir().'/sp-lic-'.bin2hex(random_bytes(4));
        mkdir($dir);
        file_put_contents($dir.'/installed.json', '{"packages": []}');
        file_put_contents($dir.'/composer.json', '{"license": "MIT"}');

        try {
            $problems = new LicenseChecker()->findProblems($dir.'/installed.json', $dir.'/composer.json');
        } finally {
            unlink($dir.'/installed.json');
            unlink($dir.'/composer.json');
            rmdir($dir);
        }

        self::assertCount(1, $problems);
    }

    public function testSharedCsRulesAreStrict(): void
    {
        self::assertTrue(CsFixerRules::rules()['declare_strict_types']);
    }

    public function testCsFixerConfigAllowsRiskyRulesAndUsesSharedRules(): void
    {
        $config = CsFixerRules::config(new Finder()->in(__DIR__));

        self::assertTrue($config->getRiskyAllowed());
        self::assertSame(CsFixerRules::rules(), $config->getRules());
    }

    public function testRefusesUnreadableInstalledPackages(): void
    {
        $dir = sys_get_temp_dir().'/sp-lic-'.bin2hex(random_bytes(4));
        mkdir($dir);
        file_put_contents($dir.'/installed.json', 'null');
        file_put_contents($dir.'/composer.json', '{"license": "GPL-2.0-or-later"}');

        try {
            $this->expectException(\RuntimeException::class);
            new LicenseChecker()->findProblems($dir.'/installed.json', $dir.'/composer.json');
        } finally {
            unlink($dir.'/installed.json');
            unlink($dir.'/composer.json');
            rmdir($dir);
        }
    }

    public function testSkipsMalformedPackageEntries(): void
    {
        $dir = sys_get_temp_dir().'/sp-lic-'.bin2hex(random_bytes(4));
        mkdir($dir);
        file_put_contents($dir.'/installed.json', '{"packages": ["oops", {"license": ["proprietary"]}]}');
        file_put_contents($dir.'/composer.json', '{"license": "GPL-2.0-or-later"}');

        try {
            $problems = new LicenseChecker()->findProblems($dir.'/installed.json', $dir.'/composer.json');
        } finally {
            unlink($dir.'/installed.json');
            unlink($dir.'/composer.json');
            rmdir($dir);
        }

        self::assertSame([], $problems);
    }
}
