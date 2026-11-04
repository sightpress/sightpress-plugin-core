<?php

declare(strict_types=1);

namespace Acme\Events\UI;

use Acme\Events\Domain\Event;
use SightPress\Plugin\Ai\Contract\Ai;
use Symfony\Component\HttpFoundation\Response;

final readonly class Controller
{
    public function __construct(Event $e, Ai $a, Response $r)
    {
    }
}
