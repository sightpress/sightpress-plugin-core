// The shared stylelint configuration for SightPress plugins and themes.
//
// Use it from your repository's stylelint.config.mjs:
//
//   export { default } from './vendor/sightpress/plugin-core/quality/stylelint/config.mjs';
//
// or extend it:
//
//   import sightpress from './vendor/sightpress/plugin-core/quality/stylelint/config.mjs';
//   export default { ...sightpress, ignoreFiles: ['public/**'] };
//
// Turning a sightpress/* rule off needs a reason in the comment
// ("/* stylelint-disable-next-line sightpress/tokens-only -- why */"), and the
// only accepted reason for tokens-only is a file that defines the tokens.

import plugins, { rules } from './plugin.mjs';

export default {
  plugins,
  rules: {
    ...rules,
    'color-no-invalid-hex': true,
    'declaration-block-no-duplicate-properties': [true, { ignore: ['consecutive-duplicates-with-different-values'] }],
    'no-invalid-double-slash-comments': true,
  },
  reportDescriptionlessDisables: true,
  reportInvalidScopeDisables: true,
  reportNeedlessDisables: true,
};
