import type { Page } from '@playwright/test';

export type StructureOptions = {
  /** The direction <html dir> must have, for example "rtl" in he-IL and ar-SA. */
  dir?: 'ltr' | 'rtl';
  /** The language <html lang> must have, for example "de-DE". */
  lang?: string;
};

/**
 * Checks the page structure every SightPress screen shares (CLAUDE.md, "Page structure"):
 * lang and dir, a title, one h1, heading order, the banner, navigation, main and
 * contentinfo landmarks, and a skip link that is the first thing to focus.
 * Returns the problems in words; an empty list means it passed.
 */
export async function checkPageStructure(page: Page, options: StructureOptions = {}): Promise<string[]> {
  const found = await page.evaluate(() => {
    const html = document.documentElement;
    const visible = (el: Element) => !el.closest('[hidden], [aria-hidden="true"], template');
    const headings = [...document.querySelectorAll('h1, h2, h3, h4, h5, h6')].filter(visible)
      .map((h) => ({ level: Number(h.tagName[1]), text: (h.textContent ?? '').trim().slice(0, 60) }));
    const landmark = (selector: string) => [...document.querySelectorAll(selector)].filter(visible)
      .filter((el) => !el.closest('article, aside, main, nav, section') || el.matches('main, [role="main"], nav, [role="navigation"]')).length;
    const focusable = [...document.querySelectorAll<HTMLElement>(
      'a[href], button, input:not([type="hidden"]), select, textarea, [tabindex]:not([tabindex="-1"]), summary, [contenteditable="true"]',
    )].find((el) => !el.matches(':disabled') && visible(el));
    const skipTarget = focusable instanceof HTMLAnchorElement && focusable.getAttribute('href')?.startsWith('#')
      ? document.getElementById(decodeURIComponent((focusable.getAttribute('href') ?? '').slice(1)))
      : null;

    return {
      lang: html.getAttribute('lang'),
      dir: html.getAttribute('dir'),
      title: document.title.trim(),
      headings,
      banner: landmark('header, [role="banner"]'),
      navigation: landmark('nav, [role="navigation"]'),
      main: landmark('main, [role="main"]'),
      contentinfo: landmark('footer, [role="contentinfo"]'),
      firstFocusable: focusable ? `${focusable.tagName.toLowerCase()} "${(focusable.textContent ?? '').trim().slice(0, 40)}"` : null,
      skipLinkTarget: skipTarget ? (skipTarget.closest('main, [role="main"]') ? 'main' : 'elsewhere') : null,
    };
  });

  const problems: string[] = [];
  if (!found.lang || !/^[a-z]{2,3}(-[A-Za-z0-9]{2,8})*$/.test(found.lang)) {
    problems.push(`<html> needs a lang attribute with a BCP 47 code; it has ${found.lang === null ? 'none' : `"${found.lang}"`}.`);
  } else if (options.lang && found.lang !== options.lang) {
    problems.push(`<html lang> is "${found.lang}", but this page is in "${options.lang}".`);
  }
  if (found.dir !== null && found.dir !== 'ltr' && found.dir !== 'rtl') {
    problems.push(`<html dir> is "${found.dir}"; use "ltr" or "rtl".`);
  }
  if (options.dir && (found.dir ?? 'ltr') !== options.dir) {
    problems.push(`<html dir> is "${found.dir ?? 'not set (left to right)'}", but this page needs "${options.dir}".`);
  }
  if (found.title === '') {
    problems.push('The page has no <title>.');
  }

  const h1 = found.headings.filter((h) => h.level === 1).length;
  if (h1 !== 1) {
    problems.push(`The page needs exactly one h1; it has ${h1}.`);
  }
  found.headings.forEach((heading, index) => {
    const previous = found.headings[index - 1];
    if (previous && heading.level > previous.level + 1) {
      problems.push(`The heading "${heading.text}" is an h${heading.level} straight after an h${previous.level}; it skips a level.`);
    }
  });

  const landmarks: [keyof typeof found, string][] = [
    ['banner', 'banner (header)'], ['navigation', 'navigation (nav)'], ['main', 'main'], ['contentinfo', 'content information (footer)'],
  ];
  for (const [key, name] of landmarks) {
    const count = found[key] as number;
    if (count === 0) {
      problems.push(`The page has no ${name} landmark.`);
    } else if (key !== 'navigation' && count > 1) {
      problems.push(`The page has ${count} ${name} landmarks; it needs exactly one.`);
    }
  }

  if (found.skipLinkTarget === null) {
    problems.push(`The first thing to focus must be a skip link to the main content; it is ${found.firstFocusable ?? 'nothing'}.`);
  } else if (found.skipLinkTarget !== 'main') {
    problems.push('The skip link must point to the main landmark or something inside it.');
  }

  return problems;
}

/** Fails with the problems in words when the page structure is wrong. */
export async function assertPageStructure(page: Page, options: StructureOptions = {}): Promise<void> {
  const problems = await checkPageStructure(page, options);
  if (problems.length > 0) {
    throw new Error(`${page.url()} has page structure problems:\n${problems.map((p) => `  - ${p}`).join('\n')}`);
  }
}
