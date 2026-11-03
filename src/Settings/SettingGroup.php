<?php

declare(strict_types=1);

namespace SightPress\Plugin\Core\Settings;

/** A fieldset: related settings under one legend. */
final readonly class SettingGroup
{
    /**
     * @param list<SettingField> $fields
     */
    public function __construct(
        public string $legendKey,
        public array $fields,
    ) {
        if ([] === $fields) {
            throw new \InvalidArgumentException('A settings group needs at least one field.');
        }
    }
}
