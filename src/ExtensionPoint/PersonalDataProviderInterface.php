<?php

declare(strict_types=1);

namespace SightPress\Plugin\Core\ExtensionPoint;

use SightPress\Plugin\Core\Value\UserId;

/** Member export and erase cover plugin data too; the generated Privacy policy lists it. */
interface PersonalDataProviderInterface
{
    /** @return array<string, mixed> */
    public function export(UserId $user): array;

    public function erase(UserId $user): void;

    public function describeProcessing(): ProcessingDescription;
}
