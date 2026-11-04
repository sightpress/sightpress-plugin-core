<?php

declare(strict_types=1);

namespace Acme\Events\Contract;

use Acme\Events\Domain\Event;
use SightPress\Plugin\Core\Value\ContentId;

interface EventReader
{
    public function find(ContentId $id): ?Event;
}
