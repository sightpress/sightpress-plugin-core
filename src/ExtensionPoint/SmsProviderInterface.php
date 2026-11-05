<?php

declare(strict_types=1);

namespace SightPress\Plugin\Core\ExtensionPoint;

/**
 * An SMS provider. Install-level: collected from plugins available on the
 * install, used only by the `notifications-helper` plugin. Credentials come only
 * from env vars, `*_FILE` or the vault, and are never logged.
 */
interface SmsProviderInterface
{
    public function id(): string;

    public function labelKey(): string;

    /** @return list<string> names of the environment variables it reads */
    public function credentialVariables(): array;

    public function send(string $toE164, string $text): void;

    /** Checks the credentials work; returns a message in words. */
    public function check(): string;

    public function describeProcessing(): ProcessingDescription;
}
