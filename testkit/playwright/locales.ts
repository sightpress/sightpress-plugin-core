/** The 11 launch UI locales (PLAN §3.1). */
export const LAUNCH_LOCALES = [
  'en-GB', 'en-US', 'pt-PT', 'pt-BR', 'es-ES', 'es-419', 'fr-FR', 'fr-CA', 'it-IT', 'de-DE', 'de-CH',
] as const;

/** Every RTL test runs in both (PLAN §3.1). They ship no UI catalogue. */
export const RTL_TEST_LOCALES = ['he-IL', 'ar-SA'] as const;

/** The locale visual and layout tests use for text expansion. */
export const TEXT_EXPANSION_LOCALE = 'de-DE';

/** The narrowest width every page must reflow at without horizontal scrolling (WCAG 1.4.10). */
export const REFLOW_WIDTH = 320;
