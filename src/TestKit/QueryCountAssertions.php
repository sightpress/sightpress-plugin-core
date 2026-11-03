<?php

declare(strict_types=1);

namespace SightPress\Plugin\Core\TestKit;

use PHPUnit\Framework\Assert;

/**
 * Every listing route has a query-count assertion, and the count is fixed
 * regardless of how many items the list shows (no N+1).
 */
trait QueryCountAssertions
{
    /**
     * @param callable(): mixed $action
     */
    final protected function assertQueryCountAtMost(int $max, QueryCounter $counter, callable $action): void
    {
        $counter->reset();
        $action();

        Assert::assertLessThanOrEqual($max, $counter->count(), \sprintf('Expected at most %d queries, got %d.', $max, $counter->count()));
    }

    /**
     * Runs the action with few and with many items and asserts the count did not grow.
     *
     * @param callable(int): mixed $actionWithItems seeds and renders the list with N items
     */
    final protected function assertQueryCountIndependentOfItems(QueryCounter $counter, callable $actionWithItems, int $few = 2, int $many = 20): void
    {
        $counter->reset();
        $actionWithItems($few);
        $withFew = $counter->count();

        $counter->reset();
        $actionWithItems($many);
        $withMany = $counter->count();

        Assert::assertSame($withFew, $withMany, \sprintf('The query count grew from %d (%d items) to %d (%d items): an N+1 query.', $withFew, $few, $withMany, $many));
    }
}
