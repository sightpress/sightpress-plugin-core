<?php

declare(strict_types=1);

namespace SightPress\Plugin\Core\ExtensionPoint;

/** A per-content option such as "Allow comments", shown in the core's labelled "Plugin options" fieldset. */
interface ContentOptionInterface
{
    public function id(): string;

    public function labelKey(): string;

    public function helpKey(): ?string;

    public function defaultValue(): bool;

    /** @return list<string> ids of the content types the option applies to; empty for all */
    public function contentTypes(): array;
}
