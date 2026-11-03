<?php

declare(strict_types=1);

namespace SightPress\Plugin\Core\Dto;

use SightPress\Plugin\Core\Value\UserId;

/**
 * Who a notification is for. Senders never choose a channel: they say who it
 * concerns and the delivery plugin applies preferences.
 */
final readonly class Recipients
{
    private const string KIND_CAPABILITY = 'capability';
    private const string KIND_USERS = 'users';
    private const string KIND_CONCERNED = 'concerned';

    /**
     * @param list<string> $values capability name, or user ids
     */
    private function __construct(public string $kind, public array $values)
    {
    }

    public static function byCapability(string $capability): self
    {
        return new self(self::KIND_CAPABILITY, [$capability]);
    }

    /**
     * @param non-empty-list<UserId> $users
     */
    public static function users(array $users): self
    {
        return new self(self::KIND_USERS, array_map(static fn (UserId $u): string => $u->toString(), $users));
    }

    public static function concernedUser(UserId $user): self
    {
        return new self(self::KIND_CONCERNED, [$user->toString()]);
    }

    public function isByCapability(): bool
    {
        return self::KIND_CAPABILITY === $this->kind;
    }

    public function isConcernedUser(): bool
    {
        return self::KIND_CONCERNED === $this->kind;
    }
}
