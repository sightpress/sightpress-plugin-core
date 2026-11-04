# SightPress stylelint rules

Shared by core, themes and every plugin. Use the whole configuration from your
`stylelint.config.mjs`:

```js
export { default } from './vendor/sightpress/plugin-core/quality/stylelint/config.mjs';
```

Install `stylelint` (16 or later) in your `package.json`. A rule can only be
turned off for a line with a reason
(`/* stylelint-disable-next-line sightpress/tokens-only -- defines the tokens */`);
a disable comment without one fails the check.

## logical-properties

No `left` or `right`: physical properties don't flip for right-to-left
languages. Use `margin-inline-start` instead of `margin-left`,
`inset-inline-end` instead of `right`, `text-align: start` instead of
`text-align: left`, `float: inline-start`, `border-start-end-radius`, and so on.
A four-value `margin`, `padding`, `inset` or `border-*` shorthand (or a
`border-radius`) whose left and right values differ is reported too.

## tokens-only

Colours and fonts come from the theme's token custom properties, so plugin
output follows the site's palette, dark mode and contrast correction. No hex,
`rgb()`, `oklch()` or named colours, and no font names in `font-family` or
`font`. `currentcolor`, `transparent`, `inherit` and system colours (for
`forced-colors`) are allowed. Only a file that defines the tokens may turn this
rule off.

## no-third-party-urls

No `url()` or `@import` from another host (`https://`, `http://` or `//`).
Ship fonts and images with the plugin or theme. `data:` URLs are allowed.

## reduced-motion

`animation`, `transition` and `scroll-behavior: smooth` go inside
`@media (prefers-reduced-motion: no-preference)`, so people who asked for less
motion get none.

## no-outline-removal

Never remove the focus outline (`outline: none`, `outline: 0`,
`outline-style: none`, `outline-width: 0`), except in a rule whose every
selector is `:focus:not(:focus-visible)` while `:focus-visible` shows a designed
focus style.
