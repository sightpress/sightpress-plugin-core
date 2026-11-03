<?php

declare(strict_types=1);

namespace SightPress\Plugin\Core\Tests\TestKit;

use PHPUnit\Framework\AssertionFailedError;
use SightPress\Plugin\Core\TestKit\PluginTestCase;
use SightPress\Plugin\Core\TestKit\QueryCounter;
use SightPress\Plugin\Core\Tests\Fixtures\ExamplePlugin\ExamplePlugin;
use SightPress\Plugin\Core\Value\SiteId;

/** The kit tests itself on a fixture plugin: the inherited contract tests must pass. */
final class ExamplePluginTest extends PluginTestCase
{
    protected function pluginRoot(): string
    {
        return \dirname(__DIR__).'/Fixtures/example-plugin';
    }

    public function testBundleReadsItsManifest(): void
    {
        $plugin = new ExamplePlugin();

        self::assertSame('example', $plugin->getPluginId());
        self::assertSame($this->pluginRoot(), $plugin->getPath());
        self::assertSame($plugin->getManifest(), $plugin->getManifest());
    }

    public function testQueryCountHelpers(): void
    {
        $counter = new class implements QueryCounter {
            public int $queries = 0;

            public function reset(): void
            {
                $this->queries = 0;
            }

            public function count(): int
            {
                return $this->queries;
            }
        };

        $this->assertQueryCountAtMost(3, $counter, static function () use ($counter): void {
            $counter->queries = 3;
        });
        $this->assertQueryCountIndependentOfItems($counter, static function (int $items) use ($counter): void {
            $counter->queries = 2;
        });

        $this->expectException(AssertionFailedError::class);
        $this->assertQueryCountIndependentOfItems($counter, static function (int $items) use ($counter): void {
            $counter->queries = $items;
        });
    }

    public function testSiteIsolationHelperAcceptsIsolatedQueriesAndRejectsLeaks(): void
    {
        /** @var array<string, list<string>> $rows */
        $rows = [];
        $seed = static function (SiteId $site) use (&$rows): array {
            $ids = [$site->toString().'-1', $site->toString().'-2'];
            $rows[$site->toString()] = $ids;

            return $ids;
        };

        $this->assertSiteIsolation($seed, static function (SiteId $site) use (&$rows): array {
            return $rows[$site->toString()];
        });

        $this->expectException(AssertionFailedError::class);
        $this->assertSiteIsolation($seed, static function () use (&$rows): array {
            return array_merge(...array_values($rows));
        });
    }
}
