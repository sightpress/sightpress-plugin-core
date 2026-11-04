import AxeBuilder from '@axe-core/playwright';
import type { Page } from '@playwright/test';

/** WCAG 2.2 A and AA, the floor for every SightPress screen. There is no option to turn rules off. */
export const WCAG_TAGS = ['wcag2a', 'wcag2aa', 'wcag21a', 'wcag21aa', 'wcag22a', 'wcag22aa'] as const;

/** The axe result fields the helpers read (a subset of axe-core's Result). */
export type AxeViolation = {
  id: string;
  impact?: string | null;
  help: string;
  helpUrl: string;
  nodes: { target: unknown[]; failureSummary?: string }[];
};

export type A11yCheckOptions = {
  /** Check only this part of the page (for example a slot's output). The suite still checks whole pages. */
  include?: string;
};

export type A11yResult = {
  violations: AxeViolation[];
  /** Checks axe couldn't decide (for example contrast over an image). They need a person to look. */
  needsReview: AxeViolation[];
};

/** Describes axe violations in words: rule, impact, what to fix and where. */
export function formatViolations(violations: AxeViolation[]): string {
  return violations
    .map((violation) => {
      const count = violation.nodes.length;
      const nodes = violation.nodes
        .map((node) => `    - ${node.target.map(String).join(' ')}${node.failureSummary ? `: ${node.failureSummary.replace(/\s*\n\s*/g, ' ')}` : ''}`)
        .join('\n');

      return `  "${violation.id}" (impact: ${violation.impact ?? 'unknown'}): ${violation.help}. ` +
        `${count} ${count === 1 ? 'element' : 'elements'}:\n${nodes}\n    More: ${violation.helpUrl}`;
    })
    .join('\n');
}

/** Runs axe-core with the WCAG 2.2 AA tags. */
export async function analyseA11y(page: Page, options: A11yCheckOptions = {}): Promise<A11yResult> {
  let builder = new AxeBuilder({ page }).withTags([...WCAG_TAGS]);
  if (options.include) {
    builder = builder.include(options.include);
  }
  const results = await builder.analyze();

  return { violations: results.violations, needsReview: results.incomplete };
}

/** Fails with every violation described in words when the page breaks WCAG 2.2 AA. */
export async function assertNoA11yViolations(page: Page, options: A11yCheckOptions = {}): Promise<A11yResult> {
  const result = await analyseA11y(page, options);
  if (result.violations.length > 0) {
    const count = result.violations.length;
    throw new Error(`${page.url()} has ${count} WCAG 2.2 AA ${count === 1 ? 'violation' : 'violations'}:\n${formatViolations(result.violations)}`);
  }

  return result;
}
