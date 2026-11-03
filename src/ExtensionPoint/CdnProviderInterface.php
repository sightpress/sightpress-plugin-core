<?php

declare(strict_types=1);

namespace SightPress\Plugin\Core\ExtensionPoint;

/**
 * A CDN provider adapter. Install-level. Plans are shown before changes,
 * re-runs are idempotent, and private media is never handed to a CDN.
 */
interface CdnProviderInterface
{
    public function id(): string;

    public function labelKey(): string;

    /** @return list<string> names of the environment variables it reads */
    public function credentialVariables(): array;

    /** The least-privilege permissions the credential needs, as plain text. */
    public function requiredPermissions(): string;

    /** @return list<string> one plain-language line per change it would make */
    public function plan(): array;

    public function apply(): void;

    public function check(): string;

    /** @param list<string> $urls */
    public function purge(array $urls): void;

    public function describeProcessing(): ProcessingDescription;
}
