<?php

declare(strict_types=1);

namespace SightPress\Plugin\Core\Settings;

/**
 * One setting. Labels and help are translation keys: the core renders the
 * form with a visible label, help linked by aria-describedby and the error summary.
 */
final readonly class SettingField
{
    /**
     * @param array<string, string> $choices value => translation key, for Choice fields
     */
    public function __construct(
        public string $name,
        public FieldType $type,
        public string $labelKey,
        public ?string $helpKey = null,
        public bool $required = false,
        public mixed $default = null,
        public array $choices = [],
        public ?string $autocomplete = null,
    ) {
        if (1 !== preg_match('/^[a-z][a-z0-9_]*$/', $name)) {
            throw new \InvalidArgumentException(\sprintf('Setting name "%s" must be lowercase snake_case.', $name));
        }
        if (FieldType::Choice === $type && [] === $choices) {
            throw new \InvalidArgumentException(\sprintf('Setting "%s" is a choice and needs choices.', $name));
        }
    }
}
