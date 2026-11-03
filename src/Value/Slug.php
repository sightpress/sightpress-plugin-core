<?php

declare(strict_types=1);

namespace SightPress\Plugin\Core\Value;

/**
 * A URL path segment: Unicode letters and digits separated by single hyphens.
 */
final readonly class Slug implements \Stringable
{
    private const int MAX_LENGTH = 190;

    private function __construct(private string $value)
    {
    }

    public static function fromString(string $value): self
    {
        if ('' === $value || mb_strlen($value) > self::MAX_LENGTH) {
            throw new \InvalidArgumentException(\sprintf('A slug must be between 1 and %d characters long.', self::MAX_LENGTH));
        }

        if (1 !== preg_match('/^[\p{L}\p{N}\p{M}]+(?:-[\p{L}\p{N}\p{M}]+)*$/u', $value)) {
            throw new \InvalidArgumentException(\sprintf('"%s" is not a valid slug. Use letters, digits and single hyphens.', $value));
        }

        return new self(mb_strtolower($value));
    }

    public function toString(): string
    {
        return $this->value;
    }

    public function equals(self $other): bool
    {
        return $this->value === $other->value;
    }

    public function __toString(): string
    {
        return $this->value;
    }
}
