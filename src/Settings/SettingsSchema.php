<?php

declare(strict_types=1);

namespace SightPress\Plugin\Core\Settings;

/** A declarative settings form. The core turns it into an accessible form. */
final readonly class SettingsSchema
{
    /**
     * @param list<SettingGroup> $groups
     */
    public function __construct(public array $groups)
    {
    }

    /** @return list<SettingField> */
    public function fields(): array
    {
        $fields = [];
        foreach ($this->groups as $group) {
            foreach ($group->fields as $field) {
                $fields[] = $field;
            }
        }

        return $fields;
    }

    /** @return array<string, mixed> field name => default */
    public function defaults(): array
    {
        $defaults = [];
        foreach ($this->fields() as $field) {
            $defaults[$field->name] = $field->default;
        }

        return $defaults;
    }
}
