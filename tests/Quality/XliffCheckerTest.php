<?php

declare(strict_types=1);

namespace SightPress\Plugin\Core\Tests\Quality;

use PHPUnit\Framework\TestCase;
use SightPress\Plugin\Core\Quality\XliffChecker;
use SightPress\Plugin\Core\Value\Locale;

final class XliffCheckerTest extends TestCase
{
    private string $dir;

    protected function setUp(): void
    {
        $this->dir = sys_get_temp_dir().'/sp-xliff-'.bin2hex(random_bytes(4));
        mkdir($this->dir);
    }

    protected function tearDown(): void
    {
        foreach (glob($this->dir.'/*') ?: [] as $file) {
            unlink($file);
        }
        rmdir($this->dir);
    }

    /** @param array<string, string> $units key => target */
    private function write(string $catalogue, array $units, string $state = 'translated'): void
    {
        $body = '';
        foreach ($units as $key => $target) {
            $body .= \sprintf(
                '<trans-unit id="%1$s"><source>%1$s</source><target state="%3$s">%2$s</target></trans-unit>',
                $key,
                htmlspecialchars($target, \ENT_XML1),
                $state,
            );
        }
        file_put_contents(
            \sprintf('%s/demo+intl-icu.%s.xlf', $this->dir, $catalogue),
            '<?xml version="1.0" encoding="UTF-8"?><xliff xmlns="urn:oasis:names:tc:xliff:document:1.2" version="1.2"><file source-language="en" target-language="'.$catalogue.'" datatype="plaintext" original="demo"><body>'.$body.'</body></file></xliff>',
        );
    }

    private function writeAll(): void
    {
        foreach (Locale::launchCatalogues() as $catalogue) {
            $isBase = !str_contains($catalogue, '_');
            $this->write($catalogue, $isBase ? ['hello' => 'Hello '.$catalogue.' world'] : []);
        }
    }

    public function testCompleteCataloguesPass(): void
    {
        $this->writeAll();

        $report = new XliffChecker()->check($this->dir, 'demo');

        self::assertSame([], $report->problems());
        self::assertTrue($report->passes(true));
    }

    public function testMissingCatalogueIsReported(): void
    {
        $this->writeAll();
        unlink($this->dir.'/demo+intl-icu.de.xlf');

        $report = new XliffChecker()->check($this->dir, 'demo');

        self::assertFalse($report->passes());
        self::assertStringContainsString('Missing catalogue', $report->problems()[0]);
    }

    public function testMissingKeyInABaseCatalogueFallsBackToAnotherLanguage(): void
    {
        $this->writeAll();
        $this->write('de', []);

        $report = new XliffChecker()->check($this->dir, 'demo');

        self::assertStringContainsString('[de] key "hello" is missing', implode("\n", $report->problems()));
    }

    public function testEmptyTargetsAreReported(): void
    {
        $this->writeAll();
        $this->write('fr', ['hello' => '  ']);
        $this->write('fr_CA', ['hello' => '']);

        $problems = implode("\n", new XliffChecker()->check($this->dir, 'demo')->problems());

        self::assertStringContainsString('[fr] key "hello" has an empty target', $problems);
        self::assertStringContainsString('[fr_CA] key "hello" has an empty target', $problems);
    }

    public function testOverrideWithUnknownKeyIsReported(): void
    {
        $this->writeAll();
        $this->write('pt_BR', ['nope' => 'Nada']);

        $problems = implode("\n", new XliffChecker()->check($this->dir, 'demo')->problems());

        self::assertStringContainsString('[pt_BR] key "nope" does not exist', $problems);
    }

    public function testUntranslatedEnglishIsReported(): void
    {
        $this->writeAll();
        $this->write('it', ['hello' => 'Hello en-GB world']);
        $this->write('en', ['hello' => 'Hello en-GB world']);

        $problems = implode("\n", new XliffChecker()->check($this->dir, 'demo')->problems());

        self::assertStringContainsString('[it] key "hello" is still the English text', $problems);
    }

    public function testPluralCategoriesPerLocale(): void
    {
        $this->writeAll();
        $this->write('en', ['n' => '{count, plural, one {# file} other {# files}}']);
        $this->write('ar', ['n' => '{count, plural, one {ملف} other {ملفات}}', 'hello' => 'مرحبا']);
        $this->write('he', ['n' => '{count, plural, one {קובץ} two {שני קבצים} other {# קבצים}}', 'hello' => 'שלום']);
        foreach (['pt', 'es', 'fr', 'it', 'de'] as $language) {
            $this->write($language, ['n' => '{count, plural, one {# '.$language.'} many {# m} other {# '.$language.'s}}', 'hello' => 'Hallo '.$language]);
        }

        $problems = new XliffChecker()->check($this->dir, 'demo', [...Locale::LAUNCH, ...Locale::RTL_TEST])->problems();

        self::assertContains('[ar] key "n" has a plural message without the "zero" category.', $problems);
        self::assertContains('[ar] key "n" has a plural message without the "few" category.', $problems);
        self::assertCount(4, array_filter($problems, static fn (string $p): bool => str_starts_with($p, '[ar]')), 'Arabic lacks zero, two, few and many.');
        self::assertSame([], array_filter($problems, static fn (string $p): bool => str_starts_with($p, '[he]') || str_starts_with($p, '[en]')));
    }

    public function testReviewStateIsTrackedAndBlocksReleases(): void
    {
        $this->writeAll();
        $this->write('es', ['hello' => 'Hola mundo'], 'needs-review-translation');

        $report = new XliffChecker()->check($this->dir, 'demo');

        self::assertSame(['es:hello'], $report->unreviewed());
        self::assertTrue($report->passes());
        self::assertFalse($report->passes(true));
    }

    public function testBrokenXmlIsReported(): void
    {
        $this->writeAll();
        file_put_contents($this->dir.'/demo+intl-icu.fr.xlf', '<xliff');

        $problems = implode("\n", new XliffChecker()->check($this->dir, 'demo')->problems());

        self::assertStringContainsString('not well-formed', $problems);
    }

    public function testDeclaredLocalesAreTheOnlyOnesChecked(): void
    {
        $this->write('en', ['hello' => 'Hello world']);
        $this->write('nl_NL', ['hello' => 'Hallo wereld']);

        $report = new XliffChecker()->check($this->dir, 'demo', ['en-GB', 'nl-NL']);

        self::assertSame([], $report->problems());
    }

    public function testTheSourceIsAlwaysChecked(): void
    {
        $this->write('nl_NL', ['hello' => 'Hallo wereld']);

        $problems = implode("\n", new XliffChecker()->check($this->dir, 'demo', ['nl-NL'])->problems());

        self::assertStringContainsString('Missing catalogue demo+intl-icu.en.xlf', $problems);
    }

    public function testRegionalCatalogueWithoutItsBaseMustBeComplete(): void
    {
        $this->write('en', ['hello' => 'Hello world', 'bye' => 'Goodbye']);
        $this->write('pt_BR', ['hello' => 'Olá mundo']);

        $problems = implode("\n", new XliffChecker()->check($this->dir, 'demo', ['en-GB', 'pt-BR'])->problems());

        self::assertStringContainsString('[pt_BR] key "bye" is missing', $problems);
    }
}
