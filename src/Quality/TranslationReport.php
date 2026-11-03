<?php

declare(strict_types=1);

namespace SightPress\Plugin\Core\Quality;

/** The result of a translation check. */
final class TranslationReport
{
    /** @var list<string> */
    private array $problems = [];

    /** @var list<string> */
    private array $unreviewed = [];

    public function addProblem(string $problem): void
    {
        $this->problems[] = $problem;
    }

    public function addUnreviewed(string $unit): void
    {
        $this->unreviewed[] = $unit;
    }

    /** @return list<string> */
    public function problems(): array
    {
        return $this->problems;
    }

    /** @return list<string> catalogue:key of targets still in state needs-review-translation */
    public function unreviewed(): array
    {
        return $this->unreviewed;
    }

    /** A release can't ship unreviewed targets. */
    public function passes(bool $release = false): bool
    {
        return [] === $this->problems && (!$release || [] === $this->unreviewed);
    }
}
