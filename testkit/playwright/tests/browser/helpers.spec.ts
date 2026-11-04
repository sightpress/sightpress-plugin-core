import { expect, test } from '@playwright/test';
import { readFileSync } from 'node:fs';
import {
  analyseA11y, assertKeyboardJourney, assertNoA11yViolations, assertPageStructure, assertReflows,
  checkPageStructure, checkReflow, tabThrough, tabTo,
} from '../../index.ts';

const html = (name: string) => readFileSync(new URL(`../fixtures/${name}.html`, import.meta.url), 'utf8');

test.describe('on a page that follows the rules', () => {
  test.beforeEach(async ({ page }) => {
    await page.setContent(html('good'));
  });

  test('every check passes', async ({ page }) => {
    await assertPageStructure(page, { dir: 'ltr', lang: 'en-GB' });
    await assertReflows(page);
    const result = await assertNoA11yViolations(page);
    expect(result.violations).toEqual([]);
    const journey = await assertKeyboardJourney(page);
    expect(journey.stops.map((s) => s.name)).toEqual([
      'link "Skip to main content"',
      'link "Home"',
      'link "Events"',
      'textbox "Email address (required)"',
      'button "Send me a reminder"',
      'link "Privacy policy"',
    ]);
  });

  test('tabTo reaches a control with the keyboard alone', async ({ page }) => {
    await tabTo(page, 'button "Send me a reminder"');
    await expect(page.getByRole('button', { name: 'Send me a reminder' })).toBeFocused();
    await expect(tabTo(page, 'button "Missing"', { maxStops: 10 })).rejects.toThrow(/Tab never reached button "Missing" in 10 presses/);
  });

  test('a scoped check looks only inside the given element', async ({ page }) => {
    await page.evaluate(() => document.querySelector('footer')?.insertAdjacentHTML('beforeend', '<img src="x.png">'));
    expect((await analyseA11y(page, { include: 'main' })).violations).toEqual([]);
    await expect(assertNoA11yViolations(page)).rejects.toThrow(/"image-alt" \(impact: critical\)/);
  });

  test('the expected language and direction are checked', async ({ page }) => {
    expect(await checkPageStructure(page, { dir: 'rtl', lang: 'he-IL' })).toEqual([
      '<html lang> is "en-GB", but this page is in "he-IL".',
      '<html dir> is "ltr", but this page needs "rtl".',
    ]);
  });
});

test.describe('on a page that breaks the rules', () => {
  test.beforeEach(async ({ page }) => {
    await page.setContent(html('bad'));
  });

  test('the structure problems are stated in words', async ({ page }) => {
    expect(await checkPageStructure(page)).toEqual([
      '<html> needs a lang attribute with a BCP 47 code; it has none.',
      'The page has no <title>.',
      'The page needs exactly one h1; it has 2.',
      'The heading "Skipped levels" is an h4 straight after an h1; it skips a level.',
      'The page has no banner (header) landmark.',
      'The page has no navigation (nav) landmark.',
      'The page has no main landmark.',
      'The page has no content information (footer) landmark.',
      'The first thing to focus must be a skip link to the main content; it is button "Open".',
    ]);
    await expect(assertPageStructure(page)).rejects.toThrow(/has page structure problems/);
  });

  test('axe violations fail with their rule names', async ({ page }) => {
    await expect(assertNoA11yViolations(page)).rejects.toThrow(/WCAG 2\.2 AA violations:[\s\S]*"html-has-lang"[\s\S]*"image-alt"/);
  });

  test('reflow problems name the wide elements', async ({ page }) => {
    const problems = await checkReflow(page);
    expect(problems).toHaveLength(1);
    expect(problems[0]).toMatch(/At 320 CSS pixels wide the page scrolls sideways .* Too wide: div\.wide/);
    await expect(assertReflows(page)).rejects.toThrow(/scrolls sideways/);
    expect(page.viewportSize()?.width).toBe(1280);
  });

  test('invisible and off-screen focus are reported', async ({ page }) => {
    const journey = await tabThrough(page);
    expect(journey.problems).toEqual([
      'button "Open" has no visible focus indicator.',
      'link "Hidden link" has no visible focus indicator.',
      'link "Hidden link" takes focus while hidden or off screen.',
    ]);
    await expect(assertKeyboardJourney(page)).rejects.toThrow(/has keyboard problems/);
  });
});

test('a keyboard trap is found', async ({ page }) => {
  await page.setContent(html('trap'));
  const journey = await tabThrough(page);
  expect(journey.problems).toEqual(['Focus can\'t leave textbox "Trap": pressing Tab keeps it there (a keyboard trap).']);
});

test('a page with more stops than the limit is reported', async ({ page }) => {
  await page.setContent(html('good'));
  const journey = await tabThrough(page, { maxStops: 2 });
  expect(journey.problems).toEqual(['Tab reached 2 stops without coming back round; the page may trap focus, or needs a higher maxStops.']);
});
