<?php

declare(strict_types=1);

namespace SightPress\Plugin\Core\ExtensionPoint;

use SightPress\Plugin\Core\Dto\NotificationRequest;

/**
 * Filled only by the `notifications` plugin, one service per site. With
 * nothing filling it, the core router drops events. Core security alerts are
 * always emailed first; delivery can add channels, never remove that email.
 */
interface NotificationDeliveryInterface
{
    public function deliver(NotificationRequest $request): void;
}
