<?php

declare(strict_types=1);

namespace SightPress\Plugin\Core\ExtensionPoint;

/**
 * The container tags the core collects extension points by. Plugins tag their
 * services with these (or use the matching attributes); the core only returns
 * services of plugins enabled on the current site.
 */
final class ExtensionTag
{
    public const string CONTENT_TYPE = 'sightpress.content_type';
    public const string LINTER_RULE = 'sightpress.linter_rule';
    public const string BLOCK_TYPE = 'sightpress.block_type';
    public const string PUBLISH_GUARD = 'sightpress.publish_guard';
    public const string FIELD_TYPE = 'sightpress.field_type';
    public const string CONTENT_OPTION = 'sightpress.content_option';
    public const string ADMIN_SECTION = 'sightpress.admin_section';
    public const string ACCOUNT_SECTION = 'sightpress.account_section';
    public const string NOTIFICATION_TYPE = 'sightpress.notification_type';
    public const string NOTIFICATION_DELIVERY = 'sightpress.notification_delivery';
    public const string PERSONAL_DATA_PROVIDER = 'sightpress.personal_data_provider';
    public const string LEGAL_SECTION = 'sightpress.legal_section';
    public const string STRUCTURED_DATA_PROVIDER = 'sightpress.structured_data_provider';
    public const string IMPORTER = 'sightpress.importer';
    public const string MEDIA_SOURCE = 'sightpress.media_source';
    public const string UNINSTALLER = 'sightpress.uninstaller';

    /** Install-level adapters: collected from plugins available on the install. */
    public const string STORAGE_ADAPTER = 'sightpress.storage_adapter';
    public const string CDN_PROVIDER = 'sightpress.cdn_provider';
    public const string SMS_PROVIDER = 'sightpress.sms_provider';
    public const string MALWARE_SCANNER = 'sightpress.malware_scanner';

    private function __construct()
    {
    }
}
