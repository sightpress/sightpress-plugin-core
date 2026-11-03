<?php

declare(strict_types=1);

namespace SightPress\Plugin\Core\ExtensionPoint;

use SightPress\Plugin\Core\Dto\Finding;
use SightPress\Plugin\Core\Dto\LintSubject;

/** A linter rule. Plugins can add rules, never turn off or downgrade core ones. */
interface LinterRuleInterface
{
    /** @return iterable<Finding> */
    public function check(LintSubject $subject): iterable;
}
