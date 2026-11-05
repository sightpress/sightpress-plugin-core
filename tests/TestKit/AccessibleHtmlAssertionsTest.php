<?php

declare(strict_types=1);

namespace SightPress\Plugin\Core\Tests\TestKit;

use PHPUnit\Framework\AssertionFailedError;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use SightPress\Plugin\Core\TestKit\AccessibleHtmlAssertions;

final class AccessibleHtmlAssertionsTest extends TestCase
{
    use AccessibleHtmlAssertions;

    private const string GOOD = <<<'HTML'
        <!DOCTYPE html>
        <html lang="en-GB" dir="ltr"><head><title>Edit page</title></head>
        <body>
        <a href="#main">Skip to main content</a>
        <header><p>SightPress</p><nav aria-label="Admin"><a href="/admin">Dashboard</a></nav><nav aria-label="Account"><a href="/me">Me</a></nav></header>
        <main id="main" tabindex="-1">
          <div tabindex="-1" autofocus><h2>There is a problem</h2><ul><li><a href="#title">Enter a title</a></li><li><a href="#parts">Choose a part</a></li></ul></div>
          <h1>Edit page</h1>
          <div aria-live="polite" role="status"></div>
          <form>
            <label for="title">Title</label>
            <p id="title-hint">Shown in search results.</p>
            <p id="title-error">Error: Enter a title</p>
            <input id="title" aria-invalid="true" aria-describedby="title-hint title-error">
            <label><input id="slug"> Address</label>
            <fieldset id="parts"><legend>Parts</legend></fieldset>
            <h2>More</h2><h3>Even more</h3>
          </form>
          <section id="saved" tabindex="-1"></section>
        </main>
        <footer><p>Footer</p></footer>
        </body></html>
        HTML;

    public function testAGoodPagePassesEveryAssertion(): void
    {
        $this->assertPageStructure(self::GOOD);
        $this->assertFieldLabelled(self::GOOD, 'title');
        $this->assertFieldLabelled(self::GOOD, 'slug');
        $this->assertDescribedBy(self::GOOD, 'title', 'Shown in search results.');
        $this->assertErrorSummary(self::GOOD, ['title' => 'Enter a title', 'parts' => 'Choose a part']);
        $this->assertFocusTarget(self::GOOD, 'saved');
        $this->assertFocusTarget(self::GOOD, 'title');
        $this->assertLiveRegion(self::GOOD);
    }

    /** @return iterable<string, array{string, string, string}> */
    public static function brokenPages(): iterable
    {
        yield 'no lang' => ['<html lang="en-GB"', '<html', '<html> needs a lang attribute.'];
        yield 'no dir' => ['dir="ltr"', '', '<html> needs dir="ltr" or dir="rtl".'];
        yield 'no skip link first' => ['<a href="#main">Skip to main content</a>', '', 'The first focusable element must be the skip link to <main>.'];
        yield 'two h1' => ['<h2>More</h2>', '<h1>More</h1>', 'The page needs exactly one <h1>.'];
        yield 'skipped level' => ['<h2>More</h2><h3>', '<h2>More</h2><h4>', 'skips a level'];
        yield 'no footer' => ['<footer><p>Footer</p></footer>', '', 'The page needs a <footer> landmark.'];
        yield 'unlabelled navs' => ['<nav aria-label="Account">', '<nav>', 'With more than one <nav>, each needs a label.'];
        yield 'no main id' => ['<main id="main" tabindex="-1">', '<main>', '<main> needs an id for the skip link.'];
        yield 'empty h1' => ['<h1>Edit page</h1>', '<h1> </h1>', 'The <h1> is empty.'];
    }

    #[DataProvider('brokenPages')]
    public function testPageStructureFailures(string $search, string $replace, string $message): void
    {
        $this->expectException(AssertionFailedError::class);
        $this->expectExceptionMessage($message);

        $this->assertPageStructure(str_replace($search, $replace, self::GOOD));
    }

    public function testAnUnlabelledFieldFails(): void
    {
        $this->expectException(AssertionFailedError::class);
        $this->expectExceptionMessage('Field "title" has no <label>.');

        $this->assertFieldLabelled(str_replace('<label for="title">Title</label>', '', self::GOOD), 'title');
    }

    public function testAMissingDescriptionFails(): void
    {
        $this->expectException(AssertionFailedError::class);
        $this->expectExceptionMessage('Field "title" is described by "title-hint", which does not exist.');

        $this->assertDescribedBy(str_replace('id="title-hint"', '', self::GOOD), 'title', 'Shown');
    }

    public function testAFieldWithoutDescriptionsFails(): void
    {
        $this->expectException(AssertionFailedError::class);
        $this->expectExceptionMessage('Field "slug" has no aria-describedby.');

        $this->assertDescribedBy(self::GOOD, 'slug', 'x');
    }

    public function testAnErrorSummaryThatDoesNotTakeFocusFails(): void
    {
        $this->expectException(AssertionFailedError::class);
        $this->expectExceptionMessage('with tabindex="-1" and autofocus');

        $this->assertErrorSummary(str_replace('<div tabindex="-1" autofocus>', '<div>', self::GOOD), ['title' => 'Enter a title']);
    }

    public function testAnErrorSummaryWithoutTheLinkFails(): void
    {
        $this->expectException(AssertionFailedError::class);
        $this->expectExceptionMessage('The error summary has no link to field "slug".');

        $this->assertErrorSummary(self::GOOD, ['slug' => 'Enter an address']);
    }

    public function testAnInvalidFieldMustSayItIsInvalid(): void
    {
        $this->expectException(AssertionFailedError::class);
        $this->expectExceptionMessage('Field "title" needs aria-invalid="true".');

        $this->assertErrorSummary(str_replace(' aria-invalid="true"', '', self::GOOD), ['title' => 'Enter a title']);
    }

    public function testAFocusTargetMustBeFocusable(): void
    {
        $this->expectException(AssertionFailedError::class);
        $this->expectExceptionMessage('The focus target "title-hint" can not take focus');

        $this->assertFocusTarget(self::GOOD, 'title-hint');
    }

    public function testTheLiveRegionMustExistOnce(): void
    {
        $this->expectException(AssertionFailedError::class);

        $this->assertLiveRegion(str_replace('aria-live="polite"', '', self::GOOD));
    }
}
