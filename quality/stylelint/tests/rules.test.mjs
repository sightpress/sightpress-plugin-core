import assert from 'node:assert/strict';
import { readFile } from 'node:fs/promises';
import { describe, it } from 'node:test';
import stylelint from 'stylelint';
import config from '../config.mjs';
import {
  findLiteralColour,
  findRemoteUrls,
  isAsymmetricShorthand,
  logicalPropertyFor,
  removesOutline,
  splitTopLevel,
  startsMotion,
} from '../plugin.mjs';

const fixture = (name) => readFile(new URL(`./fixtures/${name}`, import.meta.url), 'utf8');

async function lint(code) {
  const { results } = await stylelint.lint({ code, config });
  return results[0].warnings;
}

const rulesOf = (warnings) => warnings.map((w) => w.rule);

describe('shared stylelint config', () => {
  it('accepts CSS that follows the rules', async () => {
    assert.deepEqual(await lint(await fixture('valid.css')), []);
  });

  it('reports every broken rule in the invalid fixture, with line numbers', async () => {
    const warnings = await lint(await fixture('invalid.css'));
    const byLine = Object.fromEntries(warnings.map((w) => [w.line, w.rule]));

    assert.deepEqual(byLine, {
      1: 'sightpress/no-third-party-urls',
      4: 'sightpress/logical-properties',
      5: 'sightpress/logical-properties',
      6: 'sightpress/logical-properties',
      7: 'sightpress/logical-properties',
      8: 'sightpress/logical-properties',
      9: 'sightpress/logical-properties',
      10: 'sightpress/logical-properties',
      11: 'sightpress/logical-properties',
      12: 'sightpress/tokens-only',
      13: 'sightpress/tokens-only',
      14: 'sightpress/tokens-only',
      15: 'sightpress/tokens-only',
      16: 'sightpress/no-third-party-urls',
      17: 'sightpress/reduced-motion',
      18: 'sightpress/no-outline-removal',
    });
    assert.equal(warnings.length, 16);
  });

  it('says what to write instead, in words', async () => {
    const [warning] = await lint('a { margin-left: 1rem; }');
    assert.match(warning.text, /"margin-left" is a physical property.*Use "margin-inline-start" instead/);
  });

  it('refuses a disable comment without a reason', async () => {
    const { results } = await stylelint.lint({
      code: '/* stylelint-disable-next-line sightpress/tokens-only */\na { color: red; }',
      config,
    });
    assert.ok(results[0].warnings.some((w) => w.rule === '--report-descriptionless-disables'));
  });
});

describe('logical-properties', () => {
  it('maps physical properties to logical ones', () => {
    assert.equal(logicalPropertyFor('margin-left'), 'margin-inline-start');
    assert.equal(logicalPropertyFor('Padding-Right'), 'padding-inline-end');
    assert.equal(logicalPropertyFor('border-left-color'), 'border-inline-start-color');
    assert.equal(logicalPropertyFor('border-right'), 'border-inline-end');
    assert.equal(logicalPropertyFor('scroll-margin-left'), 'scroll-margin-inline-start');
    assert.equal(logicalPropertyFor('left'), 'inset-inline-start');
    assert.equal(logicalPropertyFor('right'), 'inset-inline-end');
    assert.equal(logicalPropertyFor('border-bottom-left-radius'), 'border-end-start-radius');
    assert.equal(logicalPropertyFor('overflow-x'), 'overflow-inline');
    assert.equal(logicalPropertyFor('margin-top'), null);
    assert.equal(logicalPropertyFor('margin-inline-start'), null);
  });

  it('flags only shorthands whose left and right differ', () => {
    assert.equal(isAsymmetricShorthand('margin', '0 1rem 0 2rem'), true);
    assert.equal(isAsymmetricShorthand('margin', '0 1rem 0 1rem'), false);
    assert.equal(isAsymmetricShorthand('padding', '1px 2px 3px'), false);
    assert.equal(isAsymmetricShorthand('inset', 'calc(1px + 2px) 0 0 auto'), true);
    assert.equal(isAsymmetricShorthand('border-radius', '4px 0'), true);
    assert.equal(isAsymmetricShorthand('border-radius', '4px 4px'), false);
    assert.equal(isAsymmetricShorthand('border-radius', '4px 4px 4px'), false);
    assert.equal(isAsymmetricShorthand('border-radius', '4px 0 4px'), true);
    assert.equal(isAsymmetricShorthand('border-radius', '4px 4px 2px 2px'), false);
    assert.equal(isAsymmetricShorthand('border-radius', '4px / 2px 0'), true);
    assert.equal(isAsymmetricShorthand('border-radius', '4px'), false);
    assert.equal(isAsymmetricShorthand('color', 'a b c d'), false);
  });

  it('ignores custom properties', async () => {
    assert.deepEqual(await lint(':root { --left: 1rem; }'), []);
  });
});

describe('tokens-only', () => {
  it('finds literal colours and ignores tokens, strings and urls', () => {
    assert.equal(findLiteralColour('#fff'), '#fff');
    assert.equal(findLiteralColour('1px solid #A0B0C0FF'), '#A0B0C0FF');
    assert.equal(findLiteralColour('oklch(70% 0.1 200)'), 'oklch()');
    assert.equal(findLiteralColour('0 0 2px rgba(0,0,0,.5)'), 'rgba()');
    assert.equal(findLiteralColour('1px solid Red'), 'red');
    assert.equal(findLiteralColour('var(--color-red)'), null);
    assert.equal(findLiteralColour('"red"'), null);
    assert.equal(findLiteralColour('url(#red)'), null);
    assert.equal(findLiteralColour('currentcolor'), null);
    assert.equal(findLiteralColour('CanvasText'), null);
  });

  it('allows font tokens and inherit, and rejects named fonts', async () => {
    assert.deepEqual(await lint('a { font-family: var(--font-body); font: 1rem/1.5 var(--font-body); }'), []);
    assert.deepEqual(rulesOf(await lint('a { font: bold 1rem system-ui; }')), ['sightpress/tokens-only']);
    assert.deepEqual(rulesOf(await lint('a { font-family: serif; }')), ['sightpress/tokens-only']);
  });
});

describe('no-third-party-urls', () => {
  it('finds remote urls only', () => {
    assert.deepEqual(findRemoteUrls('url("https://a.example/x.woff2") format("woff2"), url(local.woff2)'), ['https://a.example/x.woff2']);
    assert.deepEqual(findRemoteUrls("url('//a.example/x.png')"), ['//a.example/x.png']);
    assert.deepEqual(findRemoteUrls('url(data:image/svg+xml;base64,AAAA)'), []);
  });

  it('checks @import with a plain string', async () => {
    assert.deepEqual(rulesOf(await lint('@import "http://a.example/x.css";')), ['sightpress/no-third-party-urls']);
    assert.deepEqual(await lint('@import "./local.css";'), []);
  });
});

describe('reduced-motion', () => {
  it('knows which declarations start motion', () => {
    assert.equal(startsMotion('animation', 'spin 1s infinite'), true);
    assert.equal(startsMotion('animation-name', 'none'), false);
    assert.equal(startsMotion('transition-property', 'opacity'), true);
    assert.equal(startsMotion('transition', '0s'), false);
    assert.equal(startsMotion('scroll-behavior', 'smooth'), true);
    assert.equal(startsMotion('scroll-behavior', 'auto'), false);
    assert.equal(startsMotion('color', 'red'), false);
  });

  it('accepts motion nested anywhere inside a no-preference query', async () => {
    assert.deepEqual(await lint('@media (prefers-reduced-motion: no-preference) { @media (width > 40em) { a { animation: spin 1s; } } }'), []);
    assert.deepEqual(rulesOf(await lint('@media (prefers-reduced-motion: reduce) { a { animation: spin 1s; } }')), ['sightpress/reduced-motion']);
  });
});

describe('no-outline-removal', () => {
  it('knows which declarations hide the outline', () => {
    assert.equal(removesOutline('outline', 'none'), true);
    assert.equal(removesOutline('outline', '0'), true);
    assert.equal(removesOutline('outline', '0px solid'), true);
    assert.equal(removesOutline('outline-style', 'none'), true);
    assert.equal(removesOutline('outline-width', '0'), true);
    assert.equal(removesOutline('outline', '2px solid var(--c)'), false);
    assert.equal(removesOutline('outline-offset', '0'), false);
  });

  it('allows removal only when every selector is :focus:not(:focus-visible)', async () => {
    assert.deepEqual(rulesOf(await lint('a:focus:not(:focus-visible), b:focus { outline: none; }')), ['sightpress/no-outline-removal']);
    assert.deepEqual(rulesOf(await lint('@media print { outline: none; }')), ['sightpress/no-outline-removal']);
  });
});

describe('splitTopLevel', () => {
  it('keeps functions and strings whole', () => {
    assert.deepEqual(splitTopLevel(' calc(1px + 2px)  "a b" 0 '), ['calc(1px + 2px)', '"a b"', '0']);
  });
});
