<?php

declare(strict_types=1);

namespace SightPress\Plugin\Core\TestKit;

use Dom\Element;
use Dom\HTMLDocument;
use PHPUnit\Framework\Assert;

/**
 * Assertions for server-rendered admin and public pages (CLAUDE.md, Testing
 * expectations): page structure, labelled fields, the error summary,
 * aria-describedby and focus targets. They read the HTML the way a browser
 * does (PHP's HTML5 parser), so they need no browser and work in any
 * WebTestCase, in the core and in plugins.
 *
 *     $html = (string) $client->getResponse()->getContent();
 *     $this->assertPageStructure($html);
 *     $this->assertErrorSummary($html, ['title' => 'Enter a title']);
 */
trait AccessibleHtmlAssertions
{
    final protected function parseHtml(string $html): HTMLDocument
    {
        return HTMLDocument::createFromString($html, \LIBXML_NOERROR | \Dom\HTML_NO_DEFAULT_NS);
    }

    /**
     * The same structure on every screen: lang and dir on <html>, a skip link
     * that is the first focusable element and points at <main>, one <h1>
     * inside <main>, and the header, nav and footer landmarks.
     */
    final protected function assertPageStructure(string $html): void
    {
        $document = $this->parseHtml($html);
        $root = $document->documentElement;
        Assert::assertNotNull($root, 'The page has no <html> element.');
        Assert::assertNotSame('', $root->getAttribute('lang') ?? '', '<html> needs a lang attribute.');
        Assert::assertContains($root->getAttribute('dir'), ['ltr', 'rtl'], '<html> needs dir="ltr" or dir="rtl".');

        $mains = $document->querySelectorAll('main');
        Assert::assertCount(1, $mains, 'The page needs exactly one <main>.');
        $main = $mains->item(0);
        \assert($main instanceof Element);
        Assert::assertNotSame('', $main->id, '<main> needs an id for the skip link.');

        $h1s = $document->querySelectorAll('h1');
        Assert::assertCount(1, $h1s, 'The page needs exactly one <h1>.');
        Assert::assertNotNull($main->querySelector('h1'), 'The <h1> belongs inside <main>.');
        Assert::assertNotSame('', trim((string) $h1s->item(0)?->textContent), 'The <h1> is empty.');

        $first = $document->querySelector('a[href], button, input:not([type="hidden"]), select, textarea, [tabindex]:not([tabindex="-1"])');
        Assert::assertNotNull($first, 'The page has nothing to focus.');
        Assert::assertSame('#'.$main->id, $first->getAttribute('href'), 'The first focusable element must be the skip link to <main>.');
        Assert::assertNotSame('', trim((string) $first->textContent), 'The skip link needs text.');

        foreach (['header', 'nav', 'footer'] as $landmark) {
            Assert::assertNotNull($document->querySelector($landmark), \sprintf('The page needs a <%s> landmark.', $landmark));
        }
        foreach ($document->querySelectorAll('nav') as $nav) {
            if (\count($document->querySelectorAll('nav')) > 1) {
                Assert::assertTrue($nav->hasAttribute('aria-label') || $nav->hasAttribute('aria-labelledby'), 'With more than one <nav>, each needs a label.');
            }
        }
        $this->assertHeadingOrder($document);
    }

    /**
     * Headings never skip a level, from the <h1> on. The error summary's <h2>
     * comes before the <h1> (the GOV.UK pattern), so earlier headings are not counted.
     */
    final protected function assertHeadingOrder(HTMLDocument|string $html): void
    {
        $document = \is_string($html) ? $this->parseHtml($html) : $html;
        $previous = null;
        foreach ($document->querySelectorAll('h1, h2, h3, h4, h5, h6') as $heading) {
            $level = (int) substr($heading->localName, 1);
            if (null === $previous && 1 !== $level) {
                continue;
            }
            $previous ??= 0;
            Assert::assertLessThanOrEqual($previous + 1, $level, \sprintf('Heading "%s" (h%d) skips a level after h%d.', trim((string) $heading->textContent), $level, $previous));
            $previous = $level;
        }
    }

    /** The field has a visible <label> with text. */
    final protected function assertFieldLabelled(string $html, string $fieldId): void
    {
        $document = $this->parseHtml($html);
        $field = $document->getElementById($fieldId);
        Assert::assertNotNull($field, \sprintf('No field with id "%s".', $fieldId));
        $label = $document->querySelector('label[for="'.$this->cssString($fieldId).'"]') ?? $this->ancestor($field, 'label');
        Assert::assertNotNull($label, \sprintf('Field "%s" has no <label>.', $fieldId));
        Assert::assertNotSame('', trim((string) $label->textContent), \sprintf('The label of field "%s" is empty.', $fieldId));
    }

    /** The field's aria-describedby points at elements, one of which says the text. */
    final protected function assertDescribedBy(string $html, string $fieldId, string $text): void
    {
        $document = $this->parseHtml($html);
        $field = $document->getElementById($fieldId);
        Assert::assertNotNull($field, \sprintf('No field with id "%s".', $fieldId));
        $ids = preg_split('/\s+/', trim((string) $field->getAttribute('aria-describedby'))) ?: [];
        $ids = array_values(array_filter($ids, static fn (string $id): bool => '' !== $id));
        Assert::assertNotSame([], $ids, \sprintf('Field "%s" has no aria-describedby.', $fieldId));

        $descriptions = [];
        foreach ($ids as $id) {
            $description = $document->getElementById($id);
            Assert::assertNotNull($description, \sprintf('Field "%s" is described by "%s", which does not exist.', $fieldId, $id));
            $descriptions[] = trim((string) preg_replace('/\s+/', ' ', (string) $description->textContent));
        }
        Assert::assertStringContainsString($text, implode(' ', $descriptions), \sprintf('No description of field "%s" says "%s".', $fieldId, $text));
    }

    /**
     * The GOV.UK error-summary pattern: first in <main>, focused on load, with
     * a heading and one link per field with an error. Each field is invalid,
     * and its inline error is tied to it with aria-describedby.
     *
     * @param array<string, string> $errors field id => the message shown
     */
    final protected function assertErrorSummary(string $html, array $errors): void
    {
        $document = $this->parseHtml($html);
        $main = $document->querySelector('main');
        Assert::assertNotNull($main, 'The page has no <main>.');
        $summary = $main->firstElementChild;
        Assert::assertNotNull($summary, '<main> is empty, so it has no error summary.');
        Assert::assertTrue($summary->hasAttribute('autofocus') && '-1' === $summary->getAttribute('tabindex'), 'The error summary must be the first thing in <main>, with tabindex="-1" and autofocus so it takes focus.');
        Assert::assertNotNull($summary->querySelector('h2'), 'The error summary needs a heading.');

        foreach ($errors as $fieldId => $message) {
            $link = $summary->querySelector('a[href="#'.$this->cssString($fieldId).'"]');
            Assert::assertNotNull($link, \sprintf('The error summary has no link to field "%s".', $fieldId));
            Assert::assertStringContainsString($message, (string) $link->textContent, \sprintf('The error summary link to "%s" must say "%s".', $fieldId, $message));
            if (null !== $document->getElementById($fieldId) && \in_array($document->getElementById($fieldId)?->localName, ['input', 'select', 'textarea'], true)) {
                Assert::assertSame('true', $document->getElementById($fieldId)?->getAttribute('aria-invalid'), \sprintf('Field "%s" needs aria-invalid="true".', $fieldId));
                $this->assertDescribedBy($html, $fieldId, $message);
            }
        }
    }

    /** After create, save or delete, focus goes to a predictable target: autofocus, or the URL fragment. */
    final protected function assertFocusTarget(string $html, string $id): void
    {
        $document = $this->parseHtml($html);
        $target = $document->getElementById($id);
        Assert::assertNotNull($target, \sprintf('No focus target with id "%s".', $id));
        $focusable = $target->hasAttribute('tabindex') || \in_array($target->localName, ['a', 'button', 'input', 'select', 'textarea', 'summary'], true);
        Assert::assertTrue($focusable, \sprintf('The focus target "%s" can not take focus: give it tabindex="-1".', $id));
    }

    /** The polite live region that announces messages exists exactly once. */
    final protected function assertLiveRegion(string $html): void
    {
        Assert::assertCount(1, $this->parseHtml($html)->querySelectorAll('[aria-live="polite"]'), 'The page needs exactly one aria-live="polite" region for messages.');
    }

    private function ancestor(Element $element, string $name): ?Element
    {
        for ($node = $element->parentElement; null !== $node; $node = $node->parentElement) {
            if ($name === $node->localName) {
                return $node;
            }
        }

        return null;
    }

    private function cssString(string $value): string
    {
        return addcslashes($value, '"\\');
    }
}
