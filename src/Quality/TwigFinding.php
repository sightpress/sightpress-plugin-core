<?php

declare(strict_types=1);

namespace SightPress\Plugin\Core\Quality;

/** One problem the Twig lint found, with where it is and what to do, in words. */
final readonly class TwigFinding implements \Stringable
{
    public const string RAW = 'raw-filter';
    public const string HARD_CODED_STRING = 'hard-coded-string';
    public const string INLINE_SCRIPT = 'inline-script';
    public const string THIRD_PARTY = 'third-party-resource';
    public const string POSITIVE_TABINDEX = 'positive-tabindex';
    public const string SYNTAX = 'syntax';

    public function __construct(
        public string $file,
        public int $line,
        public string $rule,
        public string $message,
    ) {
    }

    public function __toString(): string
    {
        return \sprintf('%s:%d: [%s] %s', $this->file, $this->line, $this->rule, $this->message);
    }
}
