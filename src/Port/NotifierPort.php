<?php

declare(strict_types=1);

namespace SightPress\Plugin\Core\Port;

use SightPress\Plugin\Core\Dto\NotificationRequest;

/**
 * Tells people about events. Senders never pick a channel: the core router
 * hands the request to the `notifications-helper` plugin where it is enabled, and
 * drops it otherwise.
 */
interface NotifierPort
{
    public function notify(NotificationRequest $request): void;
}
