<?php

declare(strict_types=1);

namespace Acme\Events;

use Acme\Events\UI\Controller;
use SightPress\Plugin\Core\AbstractPlugin;
use SightPress\Plugin\Core\Value\SiteId;

final class AcmeEventsPlugin extends AbstractPlugin
{
    public function controller(Controller $c, SiteId $s): void
    {
    }
}
