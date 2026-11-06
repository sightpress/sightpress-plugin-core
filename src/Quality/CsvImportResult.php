<?php

declare(strict_types=1);

namespace SightPress\Plugin\Core\Quality;

/** What importing one CSV file into a catalogue did, or would do. */
final class CsvImportResult
{
    public int $added = 0;
    public int $changed = 0;
    public int $stateChanged = 0;
    public int $unchanged = 0;
    public int $empty = 0;

    /** @var list<string> */
    private array $problems = [];

    public function __construct(public readonly string $catalogue, public readonly string $file)
    {
    }

    public function addProblem(string $problem): void
    {
        $this->problems[] = $problem;
    }

    /** @return list<string> */
    public function problems(): array
    {
        return $this->problems;
    }

    /** A file with problems is never written. */
    public function passes(): bool
    {
        return [] === $this->problems;
    }

    public function summary(): string
    {
        return \sprintf(
            '%d added, %d changed, %d with only a new state, %d unchanged, %d with an empty target left as they were',
            $this->added,
            $this->changed,
            $this->stateChanged,
            $this->unchanged,
            $this->empty,
        );
    }
}
