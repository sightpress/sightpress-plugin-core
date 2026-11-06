<?php

declare(strict_types=1);

namespace SightPress\Plugin\Core\Tests\Quality;

use PHPUnit\Framework\TestCase;
use SightPress\Plugin\Core\Quality\TranslationCsv;
use SightPress\Plugin\Core\Quality\XliffCatalogue;
use SightPress\Plugin\Core\Value\Locale;

final class TranslationCsvTest extends TestCase
{
    private string $dir;

    protected function setUp(): void
    {
        $this->dir = sys_get_temp_dir().'/sp-csv-'.bin2hex(random_bytes(4));
        mkdir($this->dir);
        $this->write('en', [
            'hello' => ['Hello', 'translated', 'neutral'],
            'pages' => ['{count, plural, one {# page} other {# pages}}', 'translated', null],
            'bye' => ['Goodbye', 'translated', null],
        ]);
        $this->write('de', [
            'hello' => ['Hallo', 'needs-review-translation', null],
            'pages' => ['{count, plural, one {# Seite} other {# Seiten}}', 'translated', null],
        ]);
        $this->write('de_CH', []);
    }

    protected function tearDown(): void
    {
        foreach (glob($this->dir.'/*') ?: [] as $file) {
            unlink($file);
        }
        rmdir($this->dir);
    }

    /** @param array<string, array{string, string, string|null}> $units key => [target, state, note] */
    private function write(string $catalogue, array $units): void
    {
        $body = '';
        foreach ($units as $key => [$target, $state, $note]) {
            $body .= \sprintf(
                "\n      <trans-unit id=\"%1\$s\" resname=\"%1\$s\">\n        <source>%1\$s</source>\n        <target state=\"%3\$s\">%2\$s</target>%4\$s\n      </trans-unit>",
                $key,
                htmlspecialchars($target, \ENT_XML1),
                $state,
                null === $note ? '' : "\n        <note>$note</note>",
            );
        }
        file_put_contents($this->file($catalogue), "<?xml version=\"1.0\" encoding=\"utf-8\"?>\n<xliff xmlns=\"urn:oasis:names:tc:xliff:document:1.2\" version=\"1.2\">\n  <file source-language=\"en-GB\" target-language=\"$catalogue\" datatype=\"plaintext\" original=\"demo\">\n    <body>$body\n    </body>\n  </file>\n</xliff>\n");
    }

    private function file(string $catalogue): string
    {
        return \sprintf('%s/demo+intl-icu.%s.xlf', $this->dir, $catalogue);
    }

    private function csv(string $contents, string $name = 'demo.de-DE.csv'): string
    {
        $file = $this->dir.'/'.$name;
        file_put_contents($file, $contents);

        return $file;
    }

    /** @return list<list<string|null>> */
    private function parse(string $csv): array
    {
        self::assertStringStartsWith("\u{FEFF}", $csv);
        $stream = fopen('php://memory', 'w+');
        self::assertIsResource($stream);
        fwrite($stream, substr($csv, 3));
        rewind($stream);
        $rows = [];
        while (false !== ($row = fgetcsv($stream, null, ',', '"', ''))) {
            $rows[] = $row;
        }
        fclose($stream);

        return $rows;
    }

    public function testExportListsEverySourceKeyWithItsTargetStateAndNote(): void
    {
        $rows = $this->parse(new TranslationCsv()->export($this->dir, 'demo', Locale::fromString('de-DE')));

        self::assertSame(TranslationCsv::COLUMNS, $rows[0]);
        self::assertSame(['hello', 'Hello', '', 'Hallo', 'needs-review-translation', 'neutral'], $rows[1]);
        self::assertSame(['pages', '{count, plural, one {# page} other {# pages}}', '', '{count, plural, one {# Seite} other {# Seiten}}', 'translated', ''], $rows[2]);
        self::assertSame(['bye', 'Goodbye', '', '', '', ''], $rows[3]);
    }

    public function testExportOfAnOverrideShowsTheBaseTextItFallsBackTo(): void
    {
        $rows = $this->parse(new TranslationCsv()->export($this->dir, 'demo', Locale::fromString('de-CH')));

        self::assertSame(['hello', 'Hello', 'Hallo', '', '', 'neutral'], $rows[1]);
    }

    public function testExportWorksWithoutTargetOrBaseCatalogue(): void
    {
        $rows = $this->parse(new TranslationCsv()->export($this->dir, 'demo', Locale::fromString('fr-CA')));

        self::assertSame(['hello', 'Hello', '', '', '', 'neutral'], $rows[1]);
    }

    public function testTheSourceLocaleIsRefused(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('source catalogue');

        new TranslationCsv()->export($this->dir, 'demo', Locale::fromString('en-GB'));
    }

    public function testExportThenImportChangesNothing(): void
    {
        $before = (string) file_get_contents($this->file('de'));
        $csv = $this->csv(new TranslationCsv()->export($this->dir, 'demo', Locale::fromString('de-DE')));

        $result = new TranslationCsv()->import($this->dir, 'demo', Locale::fromString('de-DE'), $csv);

        self::assertTrue($result->passes());
        self::assertSame([0, 0, 0, 2, 1], [$result->added, $result->changed, $result->stateChanged, $result->unchanged, $result->empty]);
        self::assertSame($before, file_get_contents($this->file('de')));
    }

    public function testAChangedTargetNeedsReviewUnlessTheRowChangesTheState(): void
    {
        $csv = $this->csv("key,target,state\nhello,Hallo!,needs-review-translation\npages,\"{count, plural, one {# Seite} other {# Seiten!}}\",translated\nbye,Tschüss,final\n");

        $result = new TranslationCsv()->import($this->dir, 'demo', Locale::fromString('de-DE'), $csv);

        self::assertTrue($result->passes(), implode("\n", $result->problems()));
        self::assertSame([1, 2], [$result->added, $result->changed]);
        $catalogue = XliffCatalogue::load($this->file('de'));
        self::assertSame(['Hallo!', 'needs-review-translation'], [$catalogue->target('hello'), $catalogue->state('hello')]);
        self::assertSame('needs-review-translation', $catalogue->state('pages'));
        self::assertSame(['Tschüss', 'final'], [$catalogue->target('bye'), $catalogue->state('bye')]);
        self::assertSame(['hello', 'pages', 'bye'], $catalogue->keys());
        self::assertStringContainsString("\n      <trans-unit id=\"bye\" resname=\"bye\">\n        <source>Goodbye</source>\n        <target state=\"final\">Tschüss</target>\n      </trans-unit>\n    </body>", (string) file_get_contents($this->file('de')));
    }

    public function testAReviewerSignsOffByChangingOnlyTheState(): void
    {
        $csv = $this->csv("key,target,state\nhello,Hallo,translated\n");

        $result = new TranslationCsv()->import($this->dir, 'demo', Locale::fromString('de-DE'), $csv);

        self::assertSame(1, $result->stateChanged);
        self::assertSame('translated', XliffCatalogue::load($this->file('de'))->state('hello'));
    }

    public function testANewTargetWithoutAStateNeedsReview(): void
    {
        $csv = $this->csv("key,target\nbye,Tschüss\n");

        new TranslationCsv()->import($this->dir, 'demo', Locale::fromString('de-DE'), $csv);

        self::assertSame('needs-review-translation', XliffCatalogue::load($this->file('de'))->state('bye'));
    }

    public function testAMissingCatalogueIsCreated(): void
    {
        $csv = $this->csv("key,target\nhello,Bonjour\n", 'demo.fr-CA.csv');

        $result = new TranslationCsv()->import($this->dir, 'demo', Locale::fromString('fr-CA'), $csv);

        self::assertSame(1, $result->added);
        $xml = (string) file_get_contents($this->file('fr_CA'));
        self::assertStringContainsString('target-language="fr-CA"', $xml);
        self::assertStringContainsString('original="demo"', $xml);
        self::assertSame('Bonjour', XliffCatalogue::load($this->file('fr_CA'))->target('hello'));
    }

    public function testSemicolonsTabsByteOrderMarkAndWindowsLineBreaksAreRead(): void
    {
        $csv = $this->csv("\u{FEFF}KEY;Target\r\nbye;\"Bis\r\nbald\"\r\n");
        new TranslationCsv()->import($this->dir, 'demo', Locale::fromString('de-DE'), $csv);
        self::assertSame("Bis\nbald", XliffCatalogue::load($this->file('de'))->target('bye'));

        $csv = $this->csv("target\tkey\nServus\tbye\n");
        new TranslationCsv()->import($this->dir, 'demo', Locale::fromString('de-DE'), $csv);
        self::assertSame('Servus', XliffCatalogue::load($this->file('de'))->target('bye'));
    }

    public function testBlankRowsAndEmptyTargetsAreLeftAlone(): void
    {
        $csv = $this->csv("key,target\n\nhello,\n");

        $result = new TranslationCsv()->import($this->dir, 'demo', Locale::fromString('de-DE'), $csv);

        self::assertTrue($result->passes());
        self::assertSame(1, $result->empty);
        self::assertSame('Hallo', XliffCatalogue::load($this->file('de'))->target('hello'));
        self::assertStringContainsString('1 with an empty target left as they were', $result->summary());
    }

    public function testADryRunWritesNothing(): void
    {
        $before = (string) file_get_contents($this->file('de'));
        $csv = $this->csv("key,target\nbye,Tschüss\n");

        $result = new TranslationCsv()->import($this->dir, 'demo', Locale::fromString('de-DE'), $csv, true);

        self::assertSame(1, $result->added);
        self::assertSame($before, file_get_contents($this->file('de')));
    }

    public function testAFileWithProblemsIsNotWritten(): void
    {
        $before = (string) file_get_contents($this->file('de'));
        $csv = $this->csv("key,target,state\nbye,Tschüss,\n,orphan,\nmissing,Fehlt,\nbye,Again,\nhello,Hi,approved\npages,\"bad\x01\",\n");

        $result = new TranslationCsv()->import($this->dir, 'demo', Locale::fromString('de-DE'), $csv);

        self::assertFalse($result->passes());
        self::assertSame([
            'Row 3 has no key.',
            'Row 4: key "missing" does not exist in the source catalogue.',
            'Row 5 repeats key "bye" from row 2.',
            'Row 6: "approved" is not an XLIFF state. Use one of: '.implode(', ', XliffCatalogue::STATES).'.',
            'Row 7: the target of key "pages" holds a control character that XML cannot store.',
        ], $result->problems());
        self::assertSame($before, file_get_contents($this->file('de')));
    }

    public function testUnreadableFilesAreReported(): void
    {
        $de = Locale::fromString('de-DE');

        self::assertStringContainsString('could not be read', new TranslationCsv()->import($this->dir, 'demo', $de, $this->dir.'/none.csv')->problems()[0]);
        self::assertStringContainsString('not UTF-8', new TranslationCsv()->import($this->dir, 'demo', $de, $this->csv("key,target\nbye,Tsch\xFCss\n"))->problems()[0]);
        self::assertStringContainsString('header row', new TranslationCsv()->import($this->dir, 'demo', $de, $this->csv("id,text\nbye,x\n"))->problems()[0]);
        self::assertStringContainsString('header row', new TranslationCsv()->import($this->dir, 'demo', $de, $this->csv(''))->problems()[0]);
    }

    public function testAMissingSourceCatalogueIsAnError(): void
    {
        unlink($this->file('en'));

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('demo+intl-icu.en.xlf is not a readable XLIFF 1.2 catalogue.');

        new TranslationCsv()->import($this->dir, 'demo', Locale::fromString('de-DE'), $this->csv("key,target\n"));
    }

    public function testACatalogueThatCannotBeWrittenIsReported(): void
    {
        if (\function_exists('posix_geteuid') && 0 === posix_geteuid()) {
            self::markTestSkipped('root can write read-only files.');
        }
        $csv = $this->csv("key,target\nbye,Tschüss\n");
        chmod($this->file('de'), 0o444);

        try {
            $result = @new TranslationCsv()->import($this->dir, 'demo', Locale::fromString('de-DE'), $csv);
        } finally {
            chmod($this->file('de'), 0o644);
        }

        self::assertSame(['demo+intl-icu.de.xlf could not be written.'], $result->problems());
    }

    public function testTheLocaleComesFromTheFileName(): void
    {
        self::assertSame('pt-BR', TranslationCsv::localeFromFileName('/tmp/demo.pt-BR.csv', 'demo')?->toString());
        self::assertSame('es-419', TranslationCsv::localeFromFileName('demo.es-419.CSV', 'demo')?->toString());
        self::assertNull(TranslationCsv::localeFromFileName('other.pt-BR.csv', 'demo'));
        self::assertNull(TranslationCsv::localeFromFileName('demo.pt-BR.txt', 'demo'));
        self::assertNull(TranslationCsv::localeFromFileName('demo.portuguese.csv', 'demo'));
        self::assertSame('demo.de-CH.csv', TranslationCsv::fileName('demo', Locale::fromString('de-CH')));
    }
}
