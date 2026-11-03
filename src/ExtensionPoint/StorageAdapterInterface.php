<?php

declare(strict_types=1);

namespace SightPress\Plugin\Core\ExtensionPoint;

/** A media storage adapter. Install-level, like SMS providers and CDN providers. */
interface StorageAdapterInterface
{
    public function id(): string;

    public function labelKey(): string;

    /** @return list<string> names of the environment variables it reads */
    public function credentialVariables(): array;

    /** Writes, reads and deletes a probe file; returns a message in words. */
    public function check(): string;
}
