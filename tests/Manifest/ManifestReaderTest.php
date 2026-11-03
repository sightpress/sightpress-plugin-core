<?php

declare(strict_types=1);

namespace SightPress\Plugin\Core\Tests\Manifest;

use PHPUnit\Framework\TestCase;
use SightPress\Plugin\Core\Manifest\ManifestException;
use SightPress\Plugin\Core\Manifest\ManifestReader;
use SightPress\Plugin\Core\Manifest\PluginManifest;
use SightPress\Plugin\Core\Value\Locale;

final class ManifestReaderTest extends TestCase
{
    /** @return array<string, mixed> */
    private function composer(string $key = '', mixed $value = null): array
    {
        $block = [
            'id' => 'forms',
            'api' => '^1.0',
            'bundle' => 'SightPress\\Plugin\\Forms\\FormsPlugin',
            'requires' => [],
            'capabilities' => ['forms.manage' => ['owner', 'admin']],
            'routes' => ['/{locale}/forms/{form}'],
            'admin_routes' => ['/admin/forms'],
            'locales' => ['en-GB', 'pt-BR'],
            'outbound_hosts' => ['api.example.org'],
        ];
        if ('' !== $key) {
            $block[$key] = $value;
        }

        return [
            'name' => 'sightpress/plugin-forms',
            'type' => 'sightpress-plugin',
            'license' => 'GPL-2.0-or-later',
            'description' => 'Forms',
            'extra' => ['sightpress' => $block],
        ];
    }

    public function testReadsAManifest(): void
    {
        $manifest = new ManifestReader()->read($this->composer());

        self::assertSame('forms', $manifest->id);
        self::assertSame('sightpress/plugin-forms', $manifest->package);
        self::assertSame('^1.0', $manifest->apiConstraint);
        self::assertSame(['owner', 'admin'], $manifest->capabilities['forms.manage']);
        self::assertTrue($manifest->declaresCapability('forms.manage'));
        self::assertFalse($manifest->declaresCapability('forms.other'));
        self::assertSame(['api.example.org'], $manifest->outboundHosts);
        self::assertSame('pt-BR', $manifest->localeObjects()[1]->toString());
        self::assertFalse($manifest->isThirdParty());
        self::assertNull($manifest->vendor());
        self::assertSame('forms', $manifest->slug());
    }

    public function testThirdPartyIdHasAVendorPrefix(): void
    {
        $composer = $this->composer('id', 'acme.event-list');

        $manifest = new ManifestReader()->read($composer);

        self::assertTrue($manifest->isThirdParty());
        self::assertSame('acme', $manifest->vendor());
        self::assertSame('acme_event_list', $manifest->slug());
    }

    public function testOfficialPluginsMustCoverEveryLaunchLocale(): void
    {
        $manifest = new ManifestReader()->read($this->composer('locales', ['en-GB', 'pt-BR', 'nl-NL']));

        self::assertSame([...Locale::LAUNCH, 'nl-NL'], $manifest->requiredLocales());
        self::assertContains('de-CH', $manifest->missingLaunchLocales());
        self::assertNotContains('pt-BR', $manifest->missingLaunchLocales());
    }

    public function testThirdPartyPluginsCoverOnlyWhatTheyDeclare(): void
    {
        $manifest = new PluginManifest('acme.events', 'acme/plugin-events', 'Acme\\Events\\EventsPlugin', '^1.0', 'GPL-2.0-or-later', 'Events', locales: ['nl-NL']);

        self::assertSame(['en-GB', 'nl-NL'], $manifest->requiredLocales());
        self::assertCount(11, $manifest->missingLaunchLocales());
    }

    public function testRefusesWrongType(): void
    {
        $composer = $this->composer();
        $composer['type'] = 'library';

        $this->expectException(ManifestException::class);
        new ManifestReader()->read($composer);
    }

    public function testRefusesMissingManifest(): void
    {
        $this->expectException(ManifestException::class);
        new ManifestReader()->read(['name' => 'a/b', 'type' => 'sightpress-plugin']);
    }

    public function testRefusesBadId(): void
    {
        $composer = $this->composer('id', 'Bad_ID');

        $this->expectException(ManifestException::class);
        new ManifestReader()->read($composer);
    }

    public function testRefusesBadLocale(): void
    {
        $composer = $this->composer('locales', ['pt_BR']);

        $this->expectException(ManifestException::class);
        new ManifestReader()->read($composer);
    }

    public function testRefusesBadCapabilities(): void
    {
        $composer = $this->composer('capabilities', ['forms.manage' => 'owner']);

        $this->expectException(ManifestException::class);
        new ManifestReader()->read($composer);
    }

    public function testRefusesNonListValues(): void
    {
        $composer = $this->composer('routes', 'x');

        $this->expectException(ManifestException::class);
        new ManifestReader()->read($composer);
    }

    public function testReadFileRefusesMissingFile(): void
    {
        $this->expectException(ManifestException::class);
        new ManifestReader()->readFile('/nonexistent/composer.json');
    }

    public function testReadFileRefusesInvalidJson(): void
    {
        $file = tempnam(sys_get_temp_dir(), 'sp');
        self::assertIsString($file);
        file_put_contents($file, '{broken');

        try {
            $this->expectException(ManifestException::class);
            new ManifestReader()->readFile($file);
        } finally {
            unlink($file);
        }
    }

    public function testReadFileReadsComposerJson(): void
    {
        $file = tempnam(sys_get_temp_dir(), 'sp');
        self::assertIsString($file);
        file_put_contents($file, json_encode($this->composer(), \JSON_THROW_ON_ERROR));

        try {
            self::assertSame('forms', new ManifestReader()->readFile($file)->id);
        } finally {
            unlink($file);
        }
    }

    public function testReadFileRefusesNonObjectJson(): void
    {
        $file = tempnam(sys_get_temp_dir(), 'sp-manifest');
        self::assertIsString($file);
        file_put_contents($file, '"just a string"');

        try {
            $this->expectException(ManifestException::class);
            $this->expectExceptionMessage('must hold a JSON object');
            new ManifestReader()->readFile($file);
        } finally {
            unlink($file);
        }
    }

    public function testRefusesNonObjectCapabilities(): void
    {
        $this->expectException(ManifestException::class);
        $this->expectExceptionMessage('"capabilities" must be an object');
        new ManifestReader()->read($this->composer('capabilities', 'owner'));
    }

    public function testRefusesListsHoldingNonStrings(): void
    {
        $this->expectException(ManifestException::class);
        $this->expectExceptionMessage('must be a list of strings');
        new ManifestReader()->read($this->composer('routes', ['/ok', 42]));
    }
}
