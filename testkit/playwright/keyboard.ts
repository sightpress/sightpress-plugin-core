import type { Page } from '@playwright/test';

/** One stop of the Tab order. */
export type KeyboardStop = {
  /** Role and accessible name, as a screen reader would announce it, for example 'link "Skip to content"'. */
  name: string;
  /** Whether the focused element shows a focus indicator (outline or box shadow). */
  focusVisible: boolean;
  /** Whether the focused element is on screen and has a size. */
  onScreen: boolean;
};

export type KeyboardJourney = {
  stops: KeyboardStop[];
  /** Problems in words: invisible focus, focus off screen, or focus that can't leave an element. */
  problems: string[];
};

export type KeyboardJourneyOptions = {
  /** Give up after this many Tab presses (default 250). Reaching it is reported as a problem. */
  maxStops?: number;
};

type FocusInfo = { id: number; focusVisible: boolean; onScreen: boolean } | null;

async function focusInfo(page: Page): Promise<FocusInfo> {
  return page.evaluate(() => {
    const el = document.activeElement;
    if (!el || el === document.body || el === document.documentElement) {
      return null;
    }
    const w = window as unknown as { __sightpressFocusIds?: WeakMap<Element, number>; __sightpressNextId?: number };
    w.__sightpressFocusIds ??= new WeakMap();
    if (!w.__sightpressFocusIds.has(el)) {
      w.__sightpressNextId = (w.__sightpressNextId ?? 0) + 1;
      w.__sightpressFocusIds.set(el, w.__sightpressNextId);
    }
    const style = getComputedStyle(el);
    const outline = style.outlineStyle !== 'none' && parseFloat(style.outlineWidth) > 0;
    const shadow = style.boxShadow !== 'none' && style.boxShadow !== '';
    const box = el.getBoundingClientRect();
    const onScreen = box.width > 0 && box.height > 0 && box.bottom > 0 && box.right > 0 &&
      box.left < document.documentElement.scrollWidth && box.top < document.documentElement.scrollHeight;

    return { id: w.__sightpressFocusIds.get(el) ?? 0, focusVisible: outline || shadow, onScreen };
  });
}

async function focusedName(page: Page): Promise<string> {
  const focused = page.locator('*:focus').first();
  // Focus may have left the page (to the browser's own controls): don't wait for an element.
  if (await focused.count() === 0) {
    return 'nothing on the page';
  }
  const snapshot = await focused.ariaSnapshot({ timeout: 2000 }).catch(() => '');
  const first = snapshot.split('\n')[0]?.replace(/^\s*-\s*/, '').replace(/:$/, '').trim() ?? '';

  return first === '' ? 'an element with no role or name' : first;
}

/**
 * Presses Tab from the top of the page until focus comes back round (or leaves
 * the page), recording every stop. Use the stops as a tab order snapshot:
 *   expect(journey.stops.map((s) => s.name)).toMatchSnapshot();
 */
export async function tabThrough(page: Page, options: KeyboardJourneyOptions = {}): Promise<KeyboardJourney> {
  const maxStops = options.maxStops ?? 250;
  await page.evaluate(() => {
    (document.activeElement as HTMLElement | null)?.blur();
    window.scrollTo(0, 0);
  });

  const stops: KeyboardStop[] = [];
  const problems: string[] = [];
  const seen = new Set<number>();
  let first: number | null = null;
  let previous: number | null = null;

  for (let presses = 0; presses < maxStops; presses++) {
    await page.keyboard.press('Tab');
    const info = await focusInfo(page);
    if (info === null) {
      // Focus went back to the document or the browser chrome: the journey is complete.
      return { stops, problems };
    }
    if (info.id === first) {
      return { stops, problems };
    }
    const name = await focusedName(page);
    if (info.id === previous) {
      problems.push(`Focus can't leave ${name}: pressing Tab keeps it there (a keyboard trap).`);
      return { stops, problems };
    }
    if (seen.has(info.id)) {
      problems.push(`Focus goes back to ${name} before reaching the rest of the page (a keyboard trap).`);
      return { stops, problems };
    }
    first ??= info.id;
    previous = info.id;
    seen.add(info.id);
    stops.push({ name, focusVisible: info.focusVisible, onScreen: info.onScreen });
    if (!info.focusVisible) {
      problems.push(`${name} has no visible focus indicator.`);
    }
    if (!info.onScreen) {
      problems.push(`${name} takes focus while hidden or off screen.`);
    }
  }
  problems.push(`Tab reached ${maxStops} stops without coming back round; the page may trap focus, or needs a higher maxStops.`);

  return { stops, problems };
}

/** Fails with the problems in words when the keyboard journey isn't clean. Returns the journey for a snapshot. */
export async function assertKeyboardJourney(page: Page, options: KeyboardJourneyOptions = {}): Promise<KeyboardJourney> {
  const journey = await tabThrough(page, options);
  if (journey.problems.length > 0) {
    throw new Error(`${page.url()} has keyboard problems:\n${journey.problems.map((p) => `  - ${p}`).join('\n')}`);
  }

  return journey;
}

/**
 * Moves focus with Tab only until it reaches the element announced as `name`
 * (for example 'button "Save"'), so keyboard journeys never click.
 */
export async function tabTo(page: Page, name: string, options: KeyboardJourneyOptions = {}): Promise<void> {
  const maxStops = options.maxStops ?? 250;
  const visited: string[] = [];
  for (let presses = 0; presses < maxStops; presses++) {
    await page.keyboard.press('Tab');
    const current = await focusedName(page);
    if (current === name) {
      return;
    }
    visited.push(current);
  }
  throw new Error(`Tab never reached ${name} in ${maxStops} presses. It reached: ${visited.slice(0, 20).join(', ')}${visited.length > 20 ? ', …' : ''}.`);
}
