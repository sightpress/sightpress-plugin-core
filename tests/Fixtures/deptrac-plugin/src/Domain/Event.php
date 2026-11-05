<?php

declare(strict_types=1);

namespace Acme\Events\Domain;

use Acme\Events\UI\Controller;
use SightPress\Core\Content\Domain\Content;
use SightPress\Plugin\AiHelper\Contract\Ai;
use SightPress\Plugin\Core\TestKit\PluginTestCase;
use SightPress\Plugin\Core\Value\SiteId;
use SightPress\Plugin\FormBuilder\Domain\Form;

final readonly class Event
{
    public function __construct(Controller $c, Content $x, SiteId $s, PluginTestCase $t, Ai $a, Form $f)
    {
    }
}
