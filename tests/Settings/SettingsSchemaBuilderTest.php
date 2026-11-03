<?php

declare(strict_types=1);

namespace SightPress\Plugin\Core\Tests\Settings;

use PHPUnit\Framework\TestCase;
use SightPress\Plugin\Core\Settings\FieldType;
use SightPress\Plugin\Core\Settings\SettingField;
use SightPress\Plugin\Core\Settings\SettingGroup;
use SightPress\Plugin\Core\Settings\SettingsSchemaBuilder;

final class SettingsSchemaBuilderTest extends TestCase
{
    public function testBuildsGroupsAndDefaults(): void
    {
        $schema = SettingsSchemaBuilder::create()
            ->group('x.general')
            ->text('sender_name', 'x.sender', 'x.sender_help', true, 'Acme', 'name')
            ->integer('per_page', 'x.per_page', default: 20)
            ->boolean('enabled', 'x.enabled', default: true)
            ->group('x.advanced')
            ->email('reply_to', 'x.reply_to')
            ->url('homepage', 'x.homepage')
            ->textArea('footer', 'x.footer')
            ->choice('mode', 'x.mode', ['a' => 'x.mode.a', 'b' => 'x.mode.b'], default: 'a')
            ->build();

        self::assertCount(2, $schema->groups);
        self::assertCount(7, $schema->fields());
        self::assertSame(['sender_name' => 'Acme', 'per_page' => 20, 'enabled' => true, 'reply_to' => null, 'homepage' => null, 'footer' => null, 'mode' => 'a'], $schema->defaults());
        self::assertSame('email', $schema->fields()[3]->autocomplete);
    }

    public function testEverySettingNeedsAGroup(): void
    {
        $this->expectException(\LogicException::class);

        SettingsSchemaBuilder::create()->text('a', 'x');
    }

    public function testDuplicateNamesAreRefused(): void
    {
        $this->expectException(\LogicException::class);

        SettingsSchemaBuilder::create()->group('g')->text('a', 'x')->text('a', 'y')->build();
    }

    public function testBadNamesAreRefused(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        new SettingField('Bad-Name', FieldType::Text, 'x');
    }

    public function testChoicesNeedOptions(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        new SettingField('mode', FieldType::Choice, 'x');
    }

    public function testGroupsNeedFields(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        new SettingGroup('g', []);
    }
}
