<?php

declare(strict_types=1);

namespace SightPress\Plugin\Core\Tests\Quality;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use SightPress\Plugin\Core\Quality\TwigFinding;
use SightPress\Plugin\Core\Quality\TwigLinter;

final class TwigLinterTest extends TestCase
{
    private const string CLEAN = __DIR__.'/../Fixtures/twig/clean';

    /** @return list<array{string, int}> rule => line */
    private function lint(string $source): array
    {
        return array_map(static fn (TwigFinding $f): array => [$f->rule, $f->line], new TwigLinter()->lintSource($source, 'test.html.twig'));
    }

    public function testACleanTemplatePasses(): void
    {
        $result = new TwigLinter()->lintDirectories([self::CLEAN]);

        self::assertSame(1, $result['files']);
        self::assertSame([], array_map(strval(...), $result['findings']));
    }

    /** @return iterable<string, array{string, list<array{string, int}>}> */
    public static function violations(): iterable
    {
        yield 'raw on anything but body_html' => ["{{ item.title|raw }}\n{{ x.body_html|upper|raw }}", [[TwigFinding::RAW, 1], [TwigFinding::RAW, 2]]];
        yield 'raw on a variable named like body_html' => ['{{ my_body_html|raw }}', [[TwigFinding::RAW, 1]]];
        yield 'raw inside a multi-line tag' => ["{{ x\n  ~ y|raw }}", [[TwigFinding::RAW, 2]]];
        yield 'raw in a string is not a filter' => ["{{ 'a|raw'|trans }}", []];
        yield 'autoescape false' => ['{% autoescape false %}{{ x }}{% endautoescape %}', [[TwigFinding::RAW, 1]]];
        yield 'apply raw' => ['{% apply upper|raw %}{{ x }}{% endapply %}', [[TwigFinding::RAW, 1]]];
        yield 'autoescape with a strategy is fine' => ["{% autoescape 'html' %}{{ x }}{% endautoescape %}", []];
        yield 'hard-coded text' => ["<p>\n  Opening hours\n</p>", [[TwigFinding::HARD_CODED_STRING, 2]]];
        yield 'hard-coded text next to Twig' => ['<p>{{ count }} items</p>', [[TwigFinding::HARD_CODED_STRING, 1]]];
        yield 'non-Latin text' => ['<p>שלום</p>', [[TwigFinding::HARD_CODED_STRING, 1]]];
        yield 'hard-coded alt' => ['<img src="a.png" alt="Logo">', [[TwigFinding::HARD_CODED_STRING, 1]]];
        yield 'hard-coded aria-label, partly Twig' => ['<a aria-label="Edit {{ title }}" href="#">{{ x }}</a>', [[TwigFinding::HARD_CODED_STRING, 1]]];
        yield 'hard-coded button value' => ['<input type="submit" value="Save">', [[TwigFinding::HARD_CODED_STRING, 1]]];
        yield 'hard-coded meta description' => ['<meta name="description" content="Our site">', [[TwigFinding::HARD_CODED_STRING, 1]]];
        yield 'text value attributes are fine' => ['<input type="text" value="abc" name="q"><option value="red">{{ x }}</option>', []];
        yield 'comments are not text' => ['{# Note #}<!-- Note -->{{ x }}', []];
        yield 'inline script' => ["<p>{{ x }}</p>\n<script>alert(1)</script>", [[TwigFinding::INLINE_SCRIPT, 2]]];
        yield 'inline script made of Twig' => ['<script>{{ code }}</script>', [[TwigFinding::INLINE_SCRIPT, 1]]];
        yield 'event handler' => ['<button onclick="go()">{{ x }}</button>', [[TwigFinding::INLINE_SCRIPT, 1]]];
        yield 'javascript URL' => ['<a href=" JavaScript:go()">{{ x }}</a>', [[TwigFinding::INLINE_SCRIPT, 1]]];
        yield 'third-party script' => ['<script src="https://cdn.example.com/x.js"></script>', [[TwigFinding::THIRD_PARTY, 1]]];
        yield 'third-party font' => ["<link rel=stylesheet href='//fonts.example.com/css'>", [[TwigFinding::THIRD_PARTY, 1]]];
        yield 'positive tabindex' => ['<div tabindex="2">{{ x }}</div><div tabindex="-1"></div><div tabindex=0></div>', [[TwigFinding::POSITIVE_TABINDEX, 1]]];
        yield 'unclosed tag' => ["<p>{{ x }}</p>\n{{ y", [[TwigFinding::SYNTAX, 2]]];
        yield 'unclosed string' => ['{{ "y }}', [[TwigFinding::SYNTAX, 1]]];
        yield 'unclosed verbatim is all text' => ['{% verbatim %}<b>{{ x }}</b>', [[TwigFinding::HARD_CODED_STRING, 1]]];
        yield 'text in verbatim is still text' => ['{% verbatim %}<p>Hi</p>{% endverbatim %}', [[TwigFinding::HARD_CODED_STRING, 1]]];
        yield 'braces that are not Twig' => ['<p>{ {{ x }} }</p>', []];
    }

    /** @param list<array{string, int}> $expected */
    #[DataProvider('violations')]
    public function testFindsViolations(string $source, array $expected): void
    {
        self::assertSame($expected, $this->lint($source));
    }

    public function testFindingsAreSortedByLineAndSayWhatToDo(): void
    {
        $findings = new TwigLinter()->lintSource("<p>Hello</p>\n{{ x|raw }}", 'events/show.html.twig');

        self::assertCount(2, $findings);
        self::assertSame('events/show.html.twig:1: [hard-coded-string] "Hello" is hard-coded text, so it can\'t be translated. Use a translation key, for example {{ \'my_plugin.label\'|trans }}.', (string) $findings[0]);
        self::assertStringContainsString('Only the core\'s sanitised "body_html" may use it', $findings[1]->message);
    }

    public function testLongTextIsShortened(): void
    {
        $findings = new TwigLinter()->lintSource('<p>'.str_repeat('word ', 30).'</p>');

        self::assertStringContainsString('word wo…"', $findings[0]->message);
    }

    public function testAnUnreadableFileIsAFinding(): void
    {
        $findings = new TwigLinter()->lintFile('/no/such/file.html.twig', 'file.html.twig');

        self::assertSame(TwigFinding::SYNTAX, $findings[0]->rule);
        self::assertSame('file.html.twig', $findings[0]->file);
    }

    public function testAMissingDirectoryIsRefused(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('"/no/such/dir" is not a directory.');

        new TwigLinter()->lintDirectories(['/no/such/dir']);
    }
}
