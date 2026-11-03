<?php

declare(strict_types=1);

namespace SightPress\Plugin\Core\ExtensionPoint;

use SightPress\Plugin\Core\Dto\LintSubject;

/** A workflow guard. It can only add reasons to block publishing, never lift them. */
interface PublishGuardInterface
{
    /**
     * @return list<string> translated reasons, in words, that block publishing; empty to allow
     */
    public function blockingReasons(LintSubject $subject): array;
}
