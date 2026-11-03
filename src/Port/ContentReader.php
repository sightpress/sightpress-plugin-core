<?php

declare(strict_types=1);

namespace SightPress\Plugin\Core\Port;

use SightPress\Plugin\Core\Dto\ContentRef;
use SightPress\Plugin\Core\Value\ContentId;
use SightPress\Plugin\Core\Value\Locale;

/** Reads content as read models. Plugins never see core entities. */
interface ContentReader
{
    public function find(ContentId $id, Locale $locale): ?ContentRef;

    /**
     * One query for many ids, so per-item extension points stay free of N+1.
     *
     * @param list<ContentId> $ids
     *
     * @return array<string, ContentRef> keyed by content id
     */
    public function findMany(array $ids, Locale $locale): array;
}
