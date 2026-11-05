<?php

declare(strict_types=1);

namespace SightPress\Plugin\Core\Tests\Quality;

use PHPUnit\Framework\Attributes\CoversNothing;
use PHPUnit\Framework\TestCase;

/**
 * Runs Deptrac with the shared plugin layers (quality/deptrac/plugin.yaml) on a
 * fixture plugin, so a change to the config can't silently stop catching a
 * forbidden dependency.
 */
#[CoversNothing]
final class DeptracPluginConfigTest extends TestCase
{
    private const string FIXTURE = __DIR__.'/../Fixtures/deptrac-plugin';

    public function testTheSharedLayersCatchEveryForbiddenDependencyAndAllowTheRest(): void
    {
        $deptrac = \dirname(__DIR__, 2).'/vendor/bin/deptrac';
        $output = sys_get_temp_dir().'/sp-deptrac-'.bin2hex(random_bytes(4)).'.json';
        $command = \sprintf(
            'cd %s && %s %s analyse --no-progress --no-cache --formatter=json --output=%s 2>&1',
            escapeshellarg(self::FIXTURE),
            escapeshellarg(\PHP_BINARY),
            escapeshellarg($deptrac),
            escapeshellarg($output),
        );
        exec($command, $lines, $exit);
        /** @var array{Report?: array{Violations?: int, Uncovered?: int}, files?: array<string, array{messages: list<array{message: string}>}>}|null $report */
        $report = is_file($output) ? json_decode((string) file_get_contents($output), true) : null;
        @unlink($output);

        self::assertIsArray($report, "Deptrac didn't write a report:\n".implode("\n", $lines));
        self::assertSame(1, $exit, 'Deptrac must fail on the fixture plugin.');

        $violations = [];
        foreach ($report['files'] ?? [] as $file) {
            foreach ($file['messages'] as $message) {
                $violations[] = preg_replace('/\s+You are depending.*$/s', '', $message['message']);
            }
        }
        sort($violations);

        self::assertSame([
            // A contract other plugins use must not expose the plugin's domain.
            'Acme\Events\Contract\EventReader must not depend on Acme\Events\Domain\Event (Contract on Domain)',
            'Acme\Events\Domain\Event must not depend on Acme\Events\UI\Controller (Domain on UI)',
            'Acme\Events\Domain\Event must not depend on SightPress\Core\Content\Domain\Content (Domain on Core)',
            // Required contracts are for Application, Infrastructure and UI, not the domain.
            'Acme\Events\Domain\Event must not depend on SightPress\Plugin\AiHelper\Contract\Ai (Domain on RequiredContract)',
            'Acme\Events\Domain\Event must not depend on SightPress\Plugin\Core\TestKit\PluginTestCase (Domain on PluginCoreDev)',
            'Acme\Events\Domain\Event must not depend on SightPress\Plugin\FormBuilder\Domain\Form (Domain on OtherPlugin)',
        ], $violations);
        self::assertSame(6, $report['Report']['Violations'] ?? null);
        self::assertSame(0, $report['Report']['Uncovered'] ?? null, 'Every dependency of the fixture must fall in a layer.');
    }
}
