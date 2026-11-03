<?php

declare(strict_types=1);

namespace SightPress\Plugin\Core\ExtensionPoint;

/** A custom content field. Translatable fields live in *Translation rows. */
interface FieldTypeInterface
{
    public function id(): string;

    public function labelKey(): string;

    public function isTranslatable(): bool;

    /**
     * Validator constraints, so every channel (admin, API, CLI, importers) enforces them.
     *
     * @return list<object>
     */
    public function constraints(): array;
}
