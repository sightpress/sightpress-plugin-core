<?php

declare(strict_types=1);

namespace SightPress\Plugin\Core\Tests\Quality;

use PHPUnit\Framework\TestCase;

/** Runs bin/sightpress-translations-csv as a translator's workflow would. */
final class TranslationsCsvCommandTest extends TestCase
{
    private string $dir;

    protected function setUp(): void
    {
        $this->dir = sys_get_temp_dir().'/sp-csv-cli-'.bin2hex(random_bytes(4));
        mkdir($this->dir.'/csv', 0o777, true);
        foreach (['en' => 'Hello', 'de' => 'Hallo'] as $catalogue => $text) {
            file_put_contents(
                \sprintf('%s/demo+intl-icu.%s.xlf', $this->dir, $catalogue),
                "<?xml version=\"1.0\" encoding=\"utf-8\"?>\n<xliff xmlns=\"urn:oasis:names:tc:xliff:document:1.2\" version=\"1.2\">\n  <file source-language=\"en-GB\" target-language=\"$catalogue\" datatype=\"plaintext\" original=\"demo\">\n    <body>\n      <trans-unit id=\"hello\" resname=\"hello\">\n        <source>Hello</source>\n        <target state=\"translated\">$text</target>\n      </trans-unit>\n    </body>\n  </file>\n</xliff>\n",
            );
        }
    }

    protected function tearDown(): void
    {
        foreach ([...glob($this->dir.'/csv/*') ?: [], ...glob($this->dir.'/*.xlf') ?: []] as $file) {
            unlink($file);
        }
        rmdir($this->dir.'/csv');
        rmdir($this->dir);
    }

    /** @return array{int, string, string} exit code, output, errors */
    private function cli(string ...$arguments): array
    {
        $process = proc_open([\PHP_BINARY, \dirname(__DIR__, 2).'/bin/sightpress-translations-csv', ...array_values($arguments)], [1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $pipes, $this->dir);
        self::assertIsResource($process);
        $output = (string) stream_get_contents($pipes[1]);
        $errors = (string) stream_get_contents($pipes[2]);

        return [proc_close($process), $output, $errors];
    }

    public function testExportWritesOneFilePerLocaleAndImportReadsThemBack(): void
    {
        [$code, $output] = $this->cli('export', '.', 'demo', '--locales=de-DE,pt-BR', '--output=csv');
        self::assertSame(0, $code, $output);
        self::assertStringContainsString('File 1 of 2: wrote csv/demo.de-DE.csv (de-DE).', $output);
        self::assertStringContainsString('Export of domain "demo": Passed. 2 CSV files written.', $output);

        file_put_contents($this->dir.'/csv/demo.de-DE.csv', "key,target\nhello,Hallo!\n");
        [$code, $output] = $this->cli('import', '.', 'demo', 'csv/demo.de-DE.csv', 'csv/demo.pt-BR.csv', '--dry-run');
        self::assertSame(0, $code, $output);
        self::assertStringContainsString('Dry run: no catalogue is written.', $output);
        self::assertStringContainsString('File 1 of 2: csv/demo.de-DE.csv into demo+intl-icu.de.xlf (de-DE): 0 added, 1 changed', $output);
        self::assertStringContainsString('Import of domain "demo": Passed.', $output);
        self::assertStringNotContainsString('Hallo!', (string) file_get_contents($this->dir.'/demo+intl-icu.de.xlf'));

        [$code] = $this->cli('import', '.', 'demo', 'csv/demo.de-DE.csv');
        self::assertSame(0, $code);
        self::assertStringContainsString('<target state="needs-review-translation">Hallo!</target>', (string) file_get_contents($this->dir.'/demo+intl-icu.de.xlf'));
    }

    public function testExportUsesTheLaunchLocalesOrTheManifestAndSkipsTheSource(): void
    {
        [$code, $output] = $this->cli('export', '.', 'demo', '--output=csv/new');
        self::assertSame(0, $code, $output);
        self::assertStringContainsString('10 CSV files written.', $output);
        self::assertFileDoesNotExist($this->dir.'/csv/new/demo.en-GB.csv');
        array_map(unlink(...), glob($this->dir.'/csv/new/*') ?: []);
        rmdir($this->dir.'/csv/new');

        file_put_contents($this->dir.'/csv/composer.json', (string) json_encode(['name' => 'acme/events', 'type' => 'sightpress-plugin', 'extra' => ['sightpress' => ['id' => 'acme.events', 'api' => '^1.0', 'bundle' => 'Acme\\Events\\EventsPlugin', 'locales' => ['en-GB', 'nl-NL']]]]));
        [$code, $output] = $this->cli('export', '.', 'demo', '--manifest=csv/composer.json', '--output=csv');
        self::assertSame(0, $code, $output);
        self::assertStringContainsString('Passed. 1 CSV file written.', $output);
        self::assertFileExists($this->dir.'/csv/demo.nl-NL.csv');
    }

    public function testProblemsFailTheImportAndLeaveTheCatalogue(): void
    {
        file_put_contents($this->dir.'/csv/demo.de-DE.csv', "key,target\nunknown,Unbekannt\n");

        [$code, $output] = $this->cli('import', '.', 'demo', 'csv/demo.de-DE.csv');

        self::assertSame(1, $code);
        self::assertStringContainsString('Problem: Row 2: key "unknown" does not exist in the source catalogue.', $output);
        self::assertStringContainsString('demo+intl-icu.de.xlf was not changed.', $output);
        self::assertStringContainsString('Import of domain "demo": Failed. 1 of 1 file had problems.', $output);
    }

    public function testUsageAndSetupErrorsExitWithTwo(): void
    {
        file_put_contents($this->dir.'/csv/german.csv', "key,target\n");

        self::assertStringContainsString('Usage:', $this->cli()[2]);
        self::assertSame(2, $this->cli('import', '.', 'demo')[0]);
        self::assertStringContainsString('The locale of csv/german.csv is unknown. Name the file "demo.<locale>.csv" (for example demo.de-DE.csv)', $this->cli('import', '.', 'demo', 'csv/german.csv')[2]);
        self::assertSame(0, $this->cli('import', '.', 'demo', 'csv/german.csv', '--locale=de-DE')[0]);
        self::assertStringContainsString('--locale works with one CSV file only', $this->cli('import', '.', 'demo', 'a.csv', 'b.csv', '--locale=de-DE')[2]);
        self::assertStringContainsString('Error: en-GB is the source catalogue', $this->cli('export', '.', 'demo', '--locales=en-GB', '--output=csv')[2]);
        file_put_contents($this->dir.'/csv/file', '');
        self::assertStringContainsString('could not be created', $this->cli('export', '.', 'demo', '--output=csv/file/sub')[2]);
    }
}
