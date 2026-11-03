<?php

declare(strict_types=1);

namespace SightPress\Plugin\Core\ExtensionPoint;

use SightPress\Plugin\Core\Value\Locale;
use SightPress\Plugin\Core\Value\SiteId;

/** A search backend behind the core SearchIndex. */
interface SearchBackendInterface
{
    public function id(): string;

    /** @param array<string, string> $fields field name => text */
    public function index(SiteId $site, string $documentId, Locale $locale, array $fields): void;

    public function remove(SiteId $site, string $documentId): void;

    /** @return list<string> matching document ids, best first */
    public function search(SiteId $site, Locale $locale, string $query, int $limit): array;
}
