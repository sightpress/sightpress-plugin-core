<?php

declare(strict_types=1);

namespace SightPress\Plugin\Core\Value;

use Symfony\Component\Uid\Ulid;

/**
 * Base for every id value type. Ids are ULIDs.
 */
abstract readonly class AbstractId implements \Stringable
{
    final private function __construct(private string $value)
    {
    }

    final public static function generate(): static
    {
        return new static(new Ulid()->toBase32());
    }

    final public static function fromString(string $value): static
    {
        if (!Ulid::isValid($value)) {
            throw new \InvalidArgumentException(\sprintf('"%s" is not a valid %s: it must be a ULID.', $value, static::class));
        }

        return new static(Ulid::fromString($value)->toBase32());
    }

    final public function toString(): string
    {
        return $this->value;
    }

    final public function equals(self $other): bool
    {
        return static::class === $other::class && $this->value === $other->value;
    }

    final public function __toString(): string
    {
        return $this->value;
    }
}
