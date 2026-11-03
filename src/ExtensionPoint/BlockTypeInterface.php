<?php

declare(strict_types=1);

namespace SightPress\Plugin\Core\ExtensionPoint;

/**
 * An editor block that round-trips through canonical Markdown without loss.
 * Plugin blocks work in page bodies and in widgets.
 */
interface BlockTypeInterface
{
    public function id(): string;

    public function labelKey(): string;

    /**
     * @param array<string, scalar|null> $attributes
     */
    public function toMarkdown(array $attributes): string;

    /**
     * @return array<string, scalar|null>|null the block's attributes, or null when the Markdown is not this block
     */
    public function fromMarkdown(string $markdown): ?array;
}
