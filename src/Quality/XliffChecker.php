<?php

declare(strict_types=1);

namespace SightPress\Plugin\Core\Quality;

use SightPress\Plugin\Core\Value\Locale;

/**
 * Checks the XLIFF catalogues of a translation domain (PLAN §3.1). By default it
 * checks the 11 launch locales, the bar for core and official plugins; pass a
 * list of locales to check only those (a third-party plugin's declared
 * locales, see PluginManifest::requiredLocales()). en-GB, the source, is
 * always checked.
 *
 *  - every checked catalogue file exists and parses;
 *  - every key of the source catalogue (en) exists in every base catalogue, with a non-empty target
 *    (a regional catalogue whose language base isn't checked, such as pt_BR without pt, counts as a base);
 *  - override catalogues (en_US, pt_BR, …) hold no empty targets and no unknown keys;
 *  - a base catalogue's target is not just the English source (it would fall back to another language),
 *    unless the key is listed as language-neutral in the source with a note "neutral";
 *  - ICU plural messages supply every plural category the locale needs;
 *  - targets in state needs-review-translation are tracked (and block a release).
 */
final class XliffChecker
{
    /** CLDR plural categories per language that ICU messages must supply. */
    private const array PLURALS = [
        'en' => ['one', 'other'],
        'de' => ['one', 'other'],
        'it' => ['one', 'many', 'other'],
        'es' => ['one', 'many', 'other'],
        'fr' => ['one', 'many', 'other'],
        'pt' => ['one', 'many', 'other'],
        'he' => ['one', 'two', 'other'],
        'ar' => ['zero', 'one', 'two', 'few', 'many', 'other'],
    ];

    /**
     * @param list<string>|null $locales BCP 47 codes to check; null checks the 11 launch locales
     */
    public function check(string $directory, string $domain, ?array $locales = null): TranslationReport
    {
        $report = new TranslationReport();
        $catalogues = [];
        $checked = null === $locales
            ? Locale::launchCatalogues()
            : array_values(array_unique(['en', ...array_map(static fn (string $code): string => Locale::fromString($code)->catalogue(), $locales)]));

        foreach ($checked as $catalogue) {
            $file = \sprintf('%s/%s+intl-icu.%s.xlf', rtrim($directory, '/'), $domain, $catalogue);
            if (!is_file($file)) {
                $report->addProblem(\sprintf('Missing catalogue %s (domain "%s", locale "%s").', basename($file), $domain, $catalogue));
                continue;
            }
            $units = $this->readUnits($file, $report);
            if (null !== $units) {
                $catalogues[$catalogue] = $units;
            }
        }

        if (!isset($catalogues['en'])) {
            return $report;
        }

        $source = $catalogues['en'];
        foreach ($catalogues as $catalogue => $units) {
            $language = explode('_', $catalogue)[0];
            $isBase = !str_contains($catalogue, '_') || !\in_array($language, $checked, true);

            if ($isBase) {
                foreach ($source as $key => $unit) {
                    if (!isset($units[$key])) {
                        $report->addProblem(\sprintf('[%s] key "%s" is missing, so it would fall back to another language.', $catalogue, $key));
                    }
                }
            }

            foreach ($units as $key => $unit) {
                if (!$isBase && !isset($source[$key])) {
                    $report->addProblem(\sprintf('[%s] key "%s" does not exist in the source catalogue.', $catalogue, $key));
                    continue;
                }
                if ('' === trim($unit['target'])) {
                    $report->addProblem(\sprintf('[%s] key "%s" has an empty target.', $catalogue, $key));
                    continue;
                }
                if ('en' !== $language && $isBase && !$unit['neutral'] && ($source[$key]['target'] ?? null) === $unit['target'] && 1 === preg_match('/\p{Latin}{3,}/u', $unit['target']) && !$this->isOnlyPlaceholders($unit['target'])) {
                    $report->addProblem(\sprintf('[%s] key "%s" is still the English text.', $catalogue, $key));
                }
                if (str_contains($unit['state'], 'needs-review')) {
                    $report->addUnreviewed(\sprintf('%s:%s', $catalogue, $key));
                }
                $this->checkPlurals($catalogue, $language, $key, $unit['target'], $report);
            }
        }

        return $report;
    }

    /**
     * @return array<string, array{target: string, state: string, neutral: bool}>|null
     */
    private function readUnits(string $file, TranslationReport $report): ?array
    {
        $dom = new \DOMDocument();
        $previous = libxml_use_internal_errors(true);
        $loaded = $dom->load($file, \LIBXML_NONET);
        libxml_clear_errors();
        libxml_use_internal_errors($previous);

        if (!$loaded) {
            $report->addProblem(\sprintf('%s is not well-formed XML.', basename($file)));

            return null;
        }

        $units = [];
        foreach ($dom->getElementsByTagName('trans-unit') as $unit) {
            $key = $unit->getAttribute('id');
            $target = $unit->getElementsByTagName('target')->item(0);
            $note = $unit->getElementsByTagName('note')->item(0);
            $units[$key] = [
                'target' => null === $target ? '' : (string) $target->textContent,
                'state' => $target?->getAttribute('state') ?? '',
                'neutral' => null !== $note && 'neutral' === trim((string) $note->textContent),
            ];
        }

        return $units;
    }

    private function isOnlyPlaceholders(string $text): bool
    {
        return '' === trim((string) preg_replace('/\{[^}]*\}/u', '', $text));
    }

    private function checkPlurals(string $catalogue, string $language, string $key, string $message, TranslationReport $report): void
    {
        if (!str_contains($message, ', plural,') && !str_contains($message, ',plural,')) {
            return;
        }
        $needed = self::PLURALS[$language] ?? ['one', 'other'];
        foreach ($needed as $category) {
            if (1 !== preg_match('/(?<![\w=])'.$category.'\s*\{/u', $message)) {
                $report->addProblem(\sprintf('[%s] key "%s" has a plural message without the "%s" category.', $catalogue, $key, $category));
            }
        }
    }
}
