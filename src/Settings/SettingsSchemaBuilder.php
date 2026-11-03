<?php

declare(strict_types=1);

namespace SightPress\Plugin\Core\Settings;

/**
 * Fluent builder for a SettingsSchema.
 *
 *     SettingsSchemaBuilder::create()
 *         ->group('forms.settings.general')
 *         ->text('sender_name', 'forms.settings.sender_name', required: true)
 *         ->build();
 */
final class SettingsSchemaBuilder
{
    /** @var list<SettingGroup> */
    private array $groups = [];

    private ?string $legendKey = null;

    /** @var list<SettingField> */
    private array $fields = [];

    private function __construct()
    {
    }

    public static function create(): self
    {
        return new self();
    }

    public function group(string $legendKey): self
    {
        $this->closeGroup();
        $this->legendKey = $legendKey;

        return $this;
    }

    public function text(string $name, string $labelKey, ?string $helpKey = null, bool $required = false, ?string $default = null, ?string $autocomplete = null): self
    {
        return $this->add(new SettingField($name, FieldType::Text, $labelKey, $helpKey, $required, $default, [], $autocomplete));
    }

    public function textArea(string $name, string $labelKey, ?string $helpKey = null, bool $required = false, ?string $default = null): self
    {
        return $this->add(new SettingField($name, FieldType::TextArea, $labelKey, $helpKey, $required, $default));
    }

    public function integer(string $name, string $labelKey, ?string $helpKey = null, bool $required = false, ?int $default = null): self
    {
        return $this->add(new SettingField($name, FieldType::Integer, $labelKey, $helpKey, $required, $default));
    }

    public function boolean(string $name, string $labelKey, ?string $helpKey = null, bool $default = false): self
    {
        return $this->add(new SettingField($name, FieldType::Boolean, $labelKey, $helpKey, false, $default));
    }

    public function email(string $name, string $labelKey, ?string $helpKey = null, bool $required = false, ?string $default = null): self
    {
        return $this->add(new SettingField($name, FieldType::Email, $labelKey, $helpKey, $required, $default, [], 'email'));
    }

    public function url(string $name, string $labelKey, ?string $helpKey = null, bool $required = false, ?string $default = null): self
    {
        return $this->add(new SettingField($name, FieldType::Url, $labelKey, $helpKey, $required, $default, [], 'url'));
    }

    /**
     * @param array<string, string> $choices value => translation key
     */
    public function choice(string $name, string $labelKey, array $choices, ?string $helpKey = null, bool $required = false, ?string $default = null): self
    {
        return $this->add(new SettingField($name, FieldType::Choice, $labelKey, $helpKey, $required, $default, $choices));
    }

    public function build(): SettingsSchema
    {
        $this->closeGroup();
        $names = [];
        foreach ($this->groups as $group) {
            foreach ($group->fields as $field) {
                if (isset($names[$field->name])) {
                    throw new \LogicException(\sprintf('Setting "%s" is declared twice.', $field->name));
                }
                $names[$field->name] = true;
            }
        }
        $schema = new SettingsSchema($this->groups);
        $this->groups = [];

        return $schema;
    }

    private function add(SettingField $field): self
    {
        if (null === $this->legendKey) {
            throw new \LogicException('Call group() before adding a setting: every setting belongs to a fieldset with a legend.');
        }
        $this->fields[] = $field;

        return $this;
    }

    private function closeGroup(): void
    {
        if (null !== $this->legendKey && [] !== $this->fields) {
            $this->groups[] = new SettingGroup($this->legendKey, $this->fields);
        }
        $this->legendKey = null;
        $this->fields = [];
    }
}
