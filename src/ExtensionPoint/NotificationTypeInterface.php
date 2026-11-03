<?php

declare(strict_types=1);

namespace SightPress\Plugin\Core\ExtensionPoint;

/**
 * A kind of notification. Types say what happened, not how to send it. Register
 * with #[AsNotificationType] and send with NotifierPort.
 */
interface NotificationTypeInterface
{
    public function id(): string;

    public function labelKey(): string;

    public function descriptionKey(): string;

    public function groupKey(): string;

    /** Capability whose holders receive it, or null for "the user it concerns". */
    public function recipientCapability(): ?string;

    /** @return list<string> channel ids this type may use: "email", "push", "sms" */
    public function allowedChannels(): array;

    /** @return list<string> */
    public function defaultChannels(): array;

    public function supportsDailySummary(): bool;

    /** Route name of the admin or account page showing the same information. */
    public function route(): string;
}
