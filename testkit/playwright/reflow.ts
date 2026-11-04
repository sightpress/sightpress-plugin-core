import type { Page } from '@playwright/test';
import { REFLOW_WIDTH } from './locales.ts';

/**
 * Checks that the page reflows at 320 CSS pixels without horizontal scrolling
 * (WCAG 1.4.10). Restores the viewport afterwards. Returns the problems in words.
 */
export async function checkReflow(page: Page, width: number = REFLOW_WIDTH): Promise<string[]> {
  const original = page.viewportSize();
  await page.setViewportSize({ width, height: original?.height ?? 800 });
  try {
    const found = await page.evaluate(() => {
      const root = document.documentElement;
      const limit = root.clientWidth;
      const wide = [...document.body.querySelectorAll<HTMLElement>('*')]
        .filter((el) => {
          const box = el.getBoundingClientRect();
          // Content that scrolls on its own (tables, code) is allowed by 1.4.10.
          return box.width > 0 && (box.right > limit + 1 || box.left < -1) && !el.closest('pre, table, [data-reflow-exempt]') &&
            getComputedStyle(el.parentElement ?? el).overflowX === 'visible';
        })
        .slice(0, 5)
        .map((el) => `${el.tagName.toLowerCase()}${el.id ? `#${el.id}` : ''}${el.classList.length ? `.${[...el.classList].join('.')}` : ''}`);

      return { scrollWidth: root.scrollWidth, clientWidth: limit, wide };
    });
    if (found.scrollWidth <= found.clientWidth + 1) {
      return [];
    }

    return [
      `At ${width} CSS pixels wide the page scrolls sideways (${found.scrollWidth} pixels of content in ${found.clientWidth}).` +
        (found.wide.length > 0 ? ` Too wide: ${found.wide.join(', ')}.` : ''),
    ];
  } finally {
    if (original) {
      await page.setViewportSize(original);
    }
  }
}

/** Fails when the page doesn't reflow at 320 CSS pixels. */
export async function assertReflows(page: Page, width: number = REFLOW_WIDTH): Promise<void> {
  const problems = await checkReflow(page, width);
  if (problems.length > 0) {
    throw new Error(`${page.url()}: ${problems.join(' ')}`);
  }
}
