<?php

declare(strict_types=1);

namespace SightPress\Plugin\Core\Attribute;

/**
 * Registers a NotificationTypeInterface. Types are sent with NotifierPort and
 * appear in preferences only where the plugin and `notifications-helper` are enabled.
 */
#[\Attribute(\Attribute::TARGET_CLASS)]
final readonly class AsNotificationType
{
    public function __construct(public string $id)
    {
    }
}
