// SightPress stylelint rules, shared by core, themes and every plugin.
//
// They enforce the CSS half of the accessibility and theming rules
// (PLAN §5.4, plugins.md §5):
//   sightpress/logical-properties    no left/right: logical properties only, so RTL works
//   sightpress/tokens-only           colours and fonts come from the theme's token custom properties
//   sightpress/no-third-party-urls   no fonts, images or imports from other hosts
//   sightpress/reduced-motion        motion only inside @media (prefers-reduced-motion: no-preference)
//   sightpress/no-outline-removal    never remove the focus outline
//
// Messages say what is wrong and what to write instead, in words.

import stylelint from 'stylelint';

const {
  createPlugin,
  utils: { report, ruleMessages, validateOptions },
} = stylelint;

const URL = 'https://github.com/sightpress/sightpress-plugin-core/blob/main/quality/stylelint/README.md';

/** Splits a value on top-level whitespace (not inside parentheses or quotes). */
export function splitTopLevel(value) {
  const parts = [];
  let depth = 0;
  let quote = null;
  let current = '';
  for (const char of value.trim()) {
    if (quote) {
      current += char;
      if (char === quote) quote = null;
      continue;
    }
    if (char === '"' || char === "'") quote = char;
    if (char === '(') depth++;
    if (char === ')') depth--;
    if (/\s/.test(char) && depth === 0) {
      if (current !== '') parts.push(current);
      current = '';
      continue;
    }
    current += char;
  }
  if (current !== '') parts.push(current);
  return parts;
}

/** Removes strings and url(...) contents so they can't trigger false positives. */
function stripStringsAndUrls(value) {
  return value
    .replace(/url\((?:[^()"']|"[^"]*"|'[^']*')*\)/gi, 'url()')
    .replace(/"[^"]*"|'[^']*'/g, '""');
}

function rule(name, messages, check) {
  const ruleName = `sightpress/${name}`;
  const ruleMessagesFor = ruleMessages(ruleName, messages);
  const ruleFunction = (primary) => (root, result) => {
    if (!validateOptions(result, ruleName, { actual: primary, possible: [true] })) {
      return;
    }
    check(root, (node, message, word) => report({ ruleName, result, node, message, word }), ruleMessagesFor);
  };
  ruleFunction.ruleName = ruleName;
  ruleFunction.messages = ruleMessagesFor;
  ruleFunction.meta = { url: `${URL}#${name}` };

  return createPlugin(ruleName, ruleFunction);
}

// --- sightpress/logical-properties ------------------------------------------

const SIDE = { left: 'inline-start', right: 'inline-end' };
const CORNER = {
  'top-left': 'start-start',
  'top-right': 'start-end',
  'bottom-left': 'end-start',
  'bottom-right': 'end-end',
};

/** Returns the logical replacement for a physical left/right property, or null. */
export function logicalPropertyFor(property) {
  const prop = property.toLowerCase();
  if (prop === 'left' || prop === 'right') {
    return `inset-${SIDE[prop]}`;
  }
  const corner = /^border-(top|bottom)-(left|right)-radius$/.exec(prop);
  if (corner) {
    return `border-${CORNER[`${corner[1]}-${corner[2]}`]}-radius`;
  }
  const side = /^(margin|padding|border|scroll-margin|scroll-padding)-(left|right)(-.+)?$/.exec(prop);
  if (side) {
    return `${side[1]}-${SIDE[side[2]]}${side[3] ?? ''}`;
  }
  if (prop === 'overflow-x' || prop === 'overscroll-behavior-x') {
    return prop.replace(/-x$/, '-inline');
  }

  return null;
}

const SIDE_VALUES = {
  'text-align': { left: 'start', right: 'end' },
  float: { left: 'inline-start', right: 'inline-end' },
  clear: { left: 'inline-start', right: 'inline-end' },
};

const FOUR_SIDED = new Set([
  'margin',
  'padding',
  'inset',
  'border-width',
  'border-style',
  'border-color',
  'scroll-margin',
  'scroll-padding',
]);

/** True when a top/right/bottom/left shorthand gives left and right different values. */
export function isAsymmetricShorthand(property, value) {
  const prop = property.toLowerCase();
  if (FOUR_SIDED.has(prop)) {
    const parts = splitTopLevel(value);
    return parts.length === 4 && parts[1] !== parts[3];
  }
  if (prop === 'border-radius') {
    return value.split('/').some((half) => {
      const p = splitTopLevel(half);
      if (p.length === 2) return p[0] !== p[1];
      if (p.length === 3) return p[0] !== p[1] || p[1] !== p[2];
      if (p.length === 4) return p[0] !== p[1] || p[2] !== p[3];
      return false;
    });
  }

  return false;
}

const logicalProperties = rule(
  'logical-properties',
  {
    property: (prop, logical) =>
      `"${prop}" is a physical property, so it doesn't flip for right-to-left languages. Use "${logical}" instead.`,
    value: (prop, value, logical) =>
      `"${prop}: ${value}" doesn't flip for right-to-left languages. Use "${prop}: ${logical}" instead.`,
    shorthand: (prop) =>
      `This "${prop}" shorthand gives the left and right sides different values, so it doesn't flip for right-to-left languages. Use the "-block" and "-inline" logical properties instead.`,
  },
  (root, warn, messages) => {
    root.walkDecls((decl) => {
      if (decl.prop.startsWith('--')) return;
      const prop = decl.prop.toLowerCase();
      const logical = logicalPropertyFor(prop);
      if (logical) {
        warn(decl, messages.property(decl.prop, logical), decl.prop);
        return;
      }
      const value = decl.value.trim().toLowerCase();
      const replacement = SIDE_VALUES[prop]?.[value];
      if (replacement) {
        warn(decl, messages.value(decl.prop, decl.value, replacement), decl.value);
        return;
      }
      if (isAsymmetricShorthand(prop, decl.value)) {
        warn(decl, messages.shorthand(decl.prop), decl.prop);
      }
    });
  },
);

// --- sightpress/tokens-only -------------------------------------------------

const NAMED_COLOURS = new Set(
  (
    'aliceblue antiquewhite aqua aquamarine azure beige bisque black blanchedalmond blue blueviolet brown ' +
    'burlywood cadetblue chartreuse chocolate coral cornflowerblue cornsilk crimson cyan darkblue darkcyan ' +
    'darkgoldenrod darkgray darkgreen darkgrey darkkhaki darkmagenta darkolivegreen darkorange darkorchid ' +
    'darkred darksalmon darkseagreen darkslateblue darkslategray darkslategrey darkturquoise darkviolet ' +
    'deeppink deepskyblue dimgray dimgrey dodgerblue firebrick floralwhite forestgreen fuchsia gainsboro ' +
    'ghostwhite gold goldenrod gray green greenyellow grey honeydew hotpink indianred indigo ivory khaki ' +
    'lavender lavenderblush lawngreen lemonchiffon lightblue lightcoral lightcyan lightgoldenrodyellow ' +
    'lightgray lightgreen lightgrey lightpink lightsalmon lightseagreen lightskyblue lightslategray ' +
    'lightslategrey lightsteelblue lightyellow lime limegreen linen magenta maroon mediumaquamarine ' +
    'mediumblue mediumorchid mediumpurple mediumseagreen mediumslateblue mediumspringgreen mediumturquoise ' +
    'mediumvioletred midnightblue mintcream mistyrose moccasin navajowhite navy oldlace olive olivedrab ' +
    'orange orangered orchid palegoldenrod palegreen paleturquoise palevioletred papayawhip peachpuff peru ' +
    'pink plum powderblue purple rebeccapurple red rosybrown royalblue saddlebrown salmon sandybrown ' +
    'seagreen seashell sienna silver skyblue slateblue slategray slategrey snow springgreen steelblue tan ' +
    'teal thistle tomato turquoise violet wheat white whitesmoke yellow yellowgreen'
  ).split(' '),
);

const COLOUR_FUNCTION = /\b(rgba?|hsla?|hwb|lab|lch|oklab|oklch|color)\(/i;
const HEX_COLOUR = /#[0-9a-f]{3,8}\b/i;
const FONT_KEYWORDS = new Set(['inherit', 'initial', 'unset', 'revert', 'revert-layer']);

/** Returns the literal colour in a value (hex, colour function or named colour), or null. */
export function findLiteralColour(value) {
  const cleaned = stripStringsAndUrls(value);
  const hex = HEX_COLOUR.exec(cleaned);
  if (hex) return hex[0];
  const fn = COLOUR_FUNCTION.exec(cleaned);
  if (fn) return `${fn[1]}()`;
  // Names in var(--name) and other identifiers are words joined by dashes; only bare words count.
  for (const word of cleaned.toLowerCase().split(/[^a-z-]+/)) {
    if (NAMED_COLOURS.has(word)) return word;
  }

  return null;
}

const tokensOnly = rule(
  'tokens-only',
  {
    colour: (colour) =>
      `"${colour}" is a fixed colour, so it can't follow the site's theme, dark mode or contrast correction. Use a theme token, for example "var(--color-text)".`,
    font: (prop) =>
      `"${prop}" names a fixed font. Use the theme's font token, for example "var(--font-body)", or "inherit".`,
  },
  (root, warn, messages) => {
    root.walkDecls((decl) => {
      const prop = decl.prop.toLowerCase();
      if (prop === 'font-family' && !FONT_KEYWORDS.has(decl.value.trim().toLowerCase()) && !/^var\(/i.test(decl.value.trim())) {
        warn(decl, messages.font(decl.prop), decl.value);
        return;
      }
      if (prop === 'font') {
        const parts = splitTopLevel(decl.value);
        const last = parts.at(-1) ?? '';
        if (parts.length > 1 && !/^var\(/i.test(last) && !FONT_KEYWORDS.has(last.toLowerCase())) {
          warn(decl, messages.font(decl.prop), decl.value);
          return;
        }
      }
      const colour = findLiteralColour(decl.value);
      if (colour) {
        warn(decl, messages.colour(colour), colour);
      }
    });
  },
);

// --- sightpress/no-third-party-urls -----------------------------------------

const REMOTE = /^\s*["']?\s*(?:https?:)?\/\//i;

/** Returns the remote URLs (http, https or protocol-relative) a value or @import loads. */
export function findRemoteUrls(value) {
  const urls = [];
  for (const match of value.matchAll(/url\(\s*((?:[^()"']|"[^"]*"|'[^']*')*)\)/gi)) {
    if (REMOTE.test(match[1])) urls.push(match[1].trim().replace(/^["']|["']$/g, ''));
  }

  return urls;
}

const noThirdPartyUrls = rule(
  'no-third-party-urls',
  {
    rejected: (url) =>
      `"${url}" loads a file from another host, which can track visitors and breaks the strict content security policy. Ship the file with the plugin or theme instead.`,
  },
  (root, warn, messages) => {
    root.walkAtRules('import', (atRule) => {
      const plain = /^\s*["']([^"']+)["']/.exec(atRule.params);
      const urls = plain && REMOTE.test(plain[1]) ? [plain[1]] : findRemoteUrls(atRule.params);
      for (const url of urls) warn(atRule, messages.rejected(url), url);
    });
    root.walkDecls((decl) => {
      for (const url of findRemoteUrls(decl.value)) warn(decl, messages.rejected(url), url);
    });
  },
);

// --- sightpress/reduced-motion ----------------------------------------------

const MOTION_OFF = new Set(['none', '0', '0s', '0ms', 'initial', 'unset', 'inherit', 'revert', 'auto']);

function insideNoPreference(node) {
  for (let parent = node.parent; parent; parent = parent.parent) {
    if (parent.type === 'atrule' && /^(media|supports|container)$/i.test(parent.name) &&
      /prefers-reduced-motion\s*:\s*no-preference/i.test(parent.params)) {
      return true;
    }
  }

  return false;
}

/** True when a declaration starts motion (an animation, a transition or smooth scrolling). */
export function startsMotion(property, value) {
  const prop = property.toLowerCase();
  const val = value.trim().toLowerCase();
  if (prop === 'scroll-behavior') return val === 'smooth';
  if (['animation', 'animation-name', 'transition', 'transition-property'].includes(prop)) {
    return !MOTION_OFF.has(val);
  }

  return false;
}

const reducedMotion = rule(
  'reduced-motion',
  {
    rejected: (prop) =>
      `"${prop}" moves things for everyone, including people who asked for less motion. Put it inside "@media (prefers-reduced-motion: no-preference)".`,
  },
  (root, warn, messages) => {
    root.walkDecls((decl) => {
      if (startsMotion(decl.prop, decl.value) && !insideNoPreference(decl)) {
        warn(decl, messages.rejected(decl.prop), decl.prop);
      }
    });
  },
);

// --- sightpress/no-outline-removal ------------------------------------------

/** True when a declaration hides the outline. */
export function removesOutline(property, value) {
  const prop = property.toLowerCase();
  const val = value.trim().toLowerCase();
  if (prop === 'outline') return /^(none|0(px|rem|em)?)(\s|$)/.test(val);
  if (prop === 'outline-style') return val === 'none';
  if (prop === 'outline-width') return /^0(px|rem|em)?$/.test(val);

  return false;
}

const noOutlineRemoval = rule(
  'no-outline-removal',
  {
    rejected: (decl) =>
      `"${decl}" hides where keyboard focus is. Keep the outline, or remove it only in ":focus:not(:focus-visible)" while ":focus-visible" shows a designed focus style.`,
  },
  (root, warn, messages) => {
    root.walkDecls((decl) => {
      if (!removesOutline(decl.prop, decl.value)) return;
      const parentRule = decl.parent?.type === 'rule' ? decl.parent : null;
      const selectors = parentRule?.selectors ?? [];
      if (selectors.length > 0 && selectors.every((s) => /:focus:not\(\s*:focus-visible\s*\)/.test(s))) return;
      warn(decl, messages.rejected(`${decl.prop}: ${decl.value}`), decl.prop);
    });
  },
);

export const rules = {
  'sightpress/logical-properties': true,
  'sightpress/tokens-only': true,
  'sightpress/no-third-party-urls': true,
  'sightpress/reduced-motion': true,
  'sightpress/no-outline-removal': true,
};

export default [logicalProperties, tokensOnly, noThirdPartyUrls, reducedMotion, noOutlineRemoval];
