<?php

declare(strict_types=1);

namespace SightPress\Plugin\Core\TestKit;

use PHPUnit\Framework\Assert;
use SightPress\Plugin\Core\Value\SiteId;

/** Every site-owned entity gets an isolation test: a query never returns another site's rows. */
trait SiteIsolationAssertions
{
    /**
     * @param callable(SiteId): list<string> $seed  creates rows for a site and returns their ids
     * @param callable(SiteId): list<string> $query reads the ids visible while that site is current
     */
    final protected function assertSiteIsolation(callable $seed, callable $query): void
    {
        $siteA = SiteId::generate();
        $siteB = SiteId::generate();

        $rowsA = $seed($siteA);
        $rowsB = $seed($siteB);
        Assert::assertNotSame([], $rowsA, 'The seed callback must create rows for each site.');

        $seenByA = $query($siteA);
        $seenByB = $query($siteB);

        Assert::assertEqualsCanonicalizing($rowsA, $seenByA, 'Site A must see exactly its own rows.');
        Assert::assertEqualsCanonicalizing($rowsB, $seenByB, 'Site B must see exactly its own rows.');
        Assert::assertSame([], array_values(array_intersect($seenByA, $rowsB)), 'Site A must never see site B rows.');
    }
}
