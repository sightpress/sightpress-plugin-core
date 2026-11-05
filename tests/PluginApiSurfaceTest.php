<?php

declare(strict_types=1);

namespace SightPress\Plugin\Core\Tests;

use PHPUnit\Framework\TestCase;
use SightPress\Plugin\Core\Attribute\AsAdminSection;
use SightPress\Plugin\Core\Attribute\AsBlockType;
use SightPress\Plugin\Core\Attribute\AsContentType;
use SightPress\Plugin\Core\Attribute\AsLinterRule;
use SightPress\Plugin\Core\Attribute\AsNotificationType;
use SightPress\Plugin\Core\Attribute\AsPublishGuard;
use SightPress\Plugin\Core\Dto\Severity;
use SightPress\Plugin\Core\Event\ContentPublished;
use SightPress\Plugin\Core\Event\MediaDeleted;
use SightPress\Plugin\Core\Event\MediaReplaced;
use SightPress\Plugin\Core\Event\MediaUploaded;
use SightPress\Plugin\Core\Event\MemberDeleted;
use SightPress\Plugin\Core\Event\SecurityAlertSent;
use SightPress\Plugin\Core\Event\WidgetPublished;
use SightPress\Plugin\Core\ExtensionPoint\ExtensionTag;
use SightPress\Plugin\Core\PluginApi;
use SightPress\Plugin\Core\Value\ContentId;
use SightPress\Plugin\Core\Value\Locale;
use SightPress\Plugin\Core\Value\MediaId;
use SightPress\Plugin\Core\Value\SiteId;
use SightPress\Plugin\Core\Value\UserId;
use SightPress\Plugin\Core\Value\WidgetId;

/**
 * The public surface of the Plugin API. A change here is a Plugin API change.
 */
final class PluginApiSurfaceTest extends TestCase
{
    private const array INTERFACES = [
        'ExtensionPoint\\AccountSectionInterface', 'ExtensionPoint\\AdminSectionInterface', 'ExtensionPoint\\BlockTypeInterface',
        'ExtensionPoint\\CdnProviderInterface', 'ExtensionPoint\\ContentOptionInterface', 'ExtensionPoint\\ContentTypeInterface',
        'ExtensionPoint\\DescriptionAssistantInterface', 'ExtensionPoint\\FieldTypeInterface', 'ExtensionPoint\\ImporterInterface',
        'ExtensionPoint\\LegalSectionInterface', 'ExtensionPoint\\LinterRuleInterface', 'ExtensionPoint\\MalwareScannerInterface',
        'ExtensionPoint\\MediaSourceInterface', 'ExtensionPoint\\NotificationDeliveryInterface', 'ExtensionPoint\\NotificationTypeInterface',
        'ExtensionPoint\\PersonalDataProviderInterface', 'ExtensionPoint\\PublishGuardInterface', 'ExtensionPoint\\SearchBackendInterface',
        'ExtensionPoint\\SeoAssistantInterface', 'ExtensionPoint\\ShareTargetInterface', 'ExtensionPoint\\SmsProviderInterface',
        'ExtensionPoint\\SpamCheckerInterface', 'ExtensionPoint\\StorageAdapterInterface', 'ExtensionPoint\\StructuredDataProviderInterface',
        'ExtensionPoint\\ThemeCritiqueInterface', 'ExtensionPoint\\UninstallerInterface',
        'Port\\ContentReader', 'Port\\CurrentUser', 'Port\\MailerPort', 'Port\\MediaLibraryPort', 'Port\\NotifierPort',
        'Port\\RateLimiterPort', 'Port\\SiteContext', 'Value\\SiteOwnedInterface', 'TestKit\\QueryCounter',
    ];

    public function testEveryDocumentedInterfaceExists(): void
    {
        foreach (self::INTERFACES as $name) {
            self::assertTrue(interface_exists('SightPress\\Plugin\\Core\\'.$name), $name.' must be an interface.');
        }
    }

    public function testApiVersionIsTheMajorOfTheConstant(): void
    {
        self::assertStringStartsWith(PluginApi::MAJOR.'.', PluginApi::VERSION);
    }

    public function testApiVersionIsThePackageVersion(): void
    {
        $manifest = json_decode((string) file_get_contents(\dirname(__DIR__).'/.release-please-manifest.json'), true, flags: \JSON_THROW_ON_ERROR);

        self::assertIsArray($manifest);
        self::assertSame($manifest['.'] ?? null, PluginApi::VERSION, 'PluginApi::VERSION must match the released package version.');
    }

    public function testExtensionTagsAreUnique(): void
    {
        $tags = new \ReflectionClass(ExtensionTag::class)->getConstants();

        self::assertSame($tags, array_unique($tags, \SORT_REGULAR));
        foreach ($tags as $tag) {
            self::assertIsString($tag);
            self::assertStringStartsWith('sightpress.', $tag);
        }
    }

    public function testAttributesCarryTheirIds(): void
    {
        self::assertSame('x.rule', new AsLinterRule('x.rule')->id);
        self::assertSame(Severity::Error, new AsLinterRule('x.rule')->severity);
        self::assertSame('x.note', new AsNotificationType('x.note')->id);
        self::assertSame('x', new AsContentType('x')->id);
        self::assertSame('x', new AsBlockType('x')->id);
        self::assertSame('x', new AsPublishGuard('x')->id);
        self::assertSame('x', new AsAdminSection('x')->id);

        foreach ([AsLinterRule::class, AsNotificationType::class, AsContentType::class, AsBlockType::class, AsPublishGuard::class, AsAdminSection::class] as $class) {
            self::assertNotSame([], new \ReflectionClass($class)->getAttributes(\Attribute::class));
        }
    }

    public function testDomainEvents(): void
    {
        $site = SiteId::generate();
        $at = new \DateTimeImmutable('2026-01-01T00:00:00Z');

        $events = [
            new ContentPublished($site, ContentId::generate(), Locale::fromString('en-GB'), 'page', $at),
            new WidgetPublished($site, WidgetId::generate(), $at),
            new MediaUploaded($site, MediaId::generate(), $at),
            new MediaReplaced($site, MediaId::generate(), $at),
            new MediaDeleted($site, MediaId::generate(), $at),
            new MemberDeleted($site, UserId::generate(), $at),
            new SecurityAlertSent($site, UserId::generate(), 'new-sign-in', $at),
        ];

        foreach ($events as $event) {
            self::assertTrue($event->siteId->equals($site));
            self::assertSame($at, $event->occurredAt);
        }
        self::assertEqualsWithDelta(time(), new MediaDeleted($site, MediaId::generate())->occurredAt->getTimestamp(), 5);
    }
}
