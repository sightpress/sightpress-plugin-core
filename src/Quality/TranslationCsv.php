<?php

declare(strict_types=1);

namespace SightPress\Plugin\Core\Quality;

use SightPress\Plugin\Core\Value\Locale;

/**
 * Exports one locale of a translation domain from its XLIFF catalogue to CSV,
 * for translators who work in a spreadsheet, and imports the CSV back.
 *
 * Columns: key, source (the en-GB text), fallback (for a regional override
 * such as pt-BR, the base catalogue's text it falls back to), target, state
 * and note. Only "key" and "target" are needed on import; "state" is
 * optional and the other columns are read-only.
 *
 * Import rules (PLAN §3.1: machine and AI drafts stay unreviewed until a
 * fluent reviewer checks them):
 *  - a changed target becomes needs-review-translation, unless the row also
 *    changes the state, which is then taken as the reviewer's decision;
 *  - an unchanged target takes the row's state when it differs (how a
 *    reviewer signs a target off);
 *  - an empty target leaves the unit as it is (nothing is ever deleted);
 *  - a key that isn't in the source catalogue, a repeated key, an unknown
 *    state, text that isn't UTF-8 or that XML can't hold is a problem, and a
 *    file with any problem is not written at all.
 */
final class TranslationCsv
{
    public const array COLUMNS = ['key', 'source', 'fallback', 'target', 'state', 'note'];

    private const string BOM = "\u{FEFF}";

    private const string UNREVIEWED = 'needs-review-translation';

    public function export(string $directory, string $domain, Locale $locale): string
    {
        $catalogue = $this->catalogueOf($locale);
        $source = XliffCatalogue::load($this->path($directory, $domain, 'en'));
        $file = $this->path($directory, $domain, $catalogue);
        $target = is_file($file) ? XliffCatalogue::load($file) : null;
        $base = null;
        if (str_contains($catalogue, '_')) {
            $baseFile = $this->path($directory, $domain, $locale->language());
            $base = is_file($baseFile) ? XliffCatalogue::load($baseFile) : null;
        }

        $stream = fopen('php://memory', 'w+');
        \assert(\is_resource($stream));
        fwrite($stream, self::BOM);
        fputcsv($stream, self::COLUMNS, ',', '"', '');
        foreach ($source->keys() as $key) {
            fputcsv($stream, [
                $key,
                $source->target($key) ?? '',
                $base?->target($key) ?? '',
                $target?->target($key) ?? '',
                $target?->state($key) ?? '',
                $source->note($key),
            ], ',', '"', '');
        }
        rewind($stream);
        $csv = (string) stream_get_contents($stream);
        fclose($stream);

        return $csv;
    }

    /** Imports a CSV file into the locale's catalogue; with $dryRun, or when there are problems, nothing is written. */
    public function import(string $directory, string $domain, Locale $locale, string $csvFile, bool $dryRun = false): CsvImportResult
    {
        $catalogue = $this->catalogueOf($locale);
        $file = $this->path($directory, $domain, $catalogue);
        $result = new CsvImportResult($catalogue, $file);
        $source = XliffCatalogue::load($this->path($directory, $domain, 'en'));
        $target = is_file($file) ? XliffCatalogue::load($file) : XliffCatalogue::create($domain, $locale->toString());

        $seen = [];
        foreach ($this->rows($csvFile, $result) as $line => $row) {
            $key = $row['key'];
            $text = str_replace("\r\n", "\n", $row['target']);
            $state = trim($row['state']);

            if ('' === $key) {
                $result->addProblem(\sprintf('Row %d has no key.', $line));
                continue;
            }
            if (isset($seen[$key])) {
                $result->addProblem(\sprintf('Row %d repeats key "%s" from row %d.', $line, $key, $seen[$key]));
                continue;
            }
            $seen[$key] = $line;
            if (!$source->has($key)) {
                $result->addProblem(\sprintf('Row %d: key "%s" does not exist in the source catalogue.', $line, $key));
                continue;
            }
            if ('' !== $state && !\in_array($state, XliffCatalogue::STATES, true)) {
                $result->addProblem(\sprintf('Row %d: "%s" is not an XLIFF state. Use one of: %s.', $line, $state, implode(', ', XliffCatalogue::STATES)));
                continue;
            }
            if (1 === preg_match('/[^\x{9}\x{A}\x{D}\x{20}-\x{D7FF}\x{E000}-\x{FFFD}\x{10000}-\x{10FFFF}]/u', $text)) {
                $result->addProblem(\sprintf('Row %d: the target of key "%s" holds a control character that XML cannot store.', $line, $key));
                continue;
            }
            if ('' === trim($text)) {
                ++$result->empty;
                continue;
            }

            $oldText = $target->target($key);
            $oldState = $target->state($key);
            $newState = '' !== $state && $state !== $oldState ? $state : ($text !== $oldText ? self::UNREVIEWED : $oldState);

            if (null === $oldText) {
                ++$result->added;
            } elseif ($text !== $oldText) {
                ++$result->changed;
            } elseif ($newState !== $oldState) {
                ++$result->stateChanged;
            } else {
                ++$result->unchanged;
                continue;
            }
            $target->set($key, $source->target($key) ?? '', $text, $newState);
        }

        if (!$dryRun && $result->passes() && $result->added + $result->changed + $result->stateChanged > 0) {
            if (false === file_put_contents($file, $target->toXml())) {
                $result->addProblem(\sprintf('%s could not be written.', basename($file)));
            }
        }

        return $result;
    }

    /** The locale named by a file called "<domain>.<locale>.csv", or null. */
    public static function localeFromFileName(string $csvFile, string $domain): ?Locale
    {
        $prefix = $domain.'.';
        $name = basename($csvFile);
        if (!str_starts_with($name, $prefix) || !str_ends_with(strtolower($name), '.csv')) {
            return null;
        }
        try {
            return Locale::fromString(substr($name, \strlen($prefix), -4));
        } catch (\InvalidArgumentException) {
            return null;
        }
    }

    public static function fileName(string $domain, Locale $locale): string
    {
        return \sprintf('%s.%s.csv', $domain, $locale->toString());
    }

    private function catalogueOf(Locale $locale): string
    {
        $catalogue = $locale->catalogue();
        if ('en' === $catalogue) {
            throw new \InvalidArgumentException(\sprintf('%s is the source catalogue (en-GB). Edit its XLIFF file directly; only the other locales go through CSV.', $locale->toString()));
        }

        return $catalogue;
    }

    private function path(string $directory, string $domain, string $catalogue): string
    {
        return \sprintf('%s/%s+intl-icu.%s.xlf', rtrim($directory, '/'), $domain, $catalogue);
    }

    /**
     * Reads the data rows, keyed by their row number as a spreadsheet shows
     * it (the header is row 1; a cell with line breaks is still one row). Accepts the comma, the
     * semicolon (what spreadsheets save in many European locales) or the tab
     * as separator, and a leading byte-order mark.
     *
     * @return array<int, array{key: string, target: string, state: string}>
     */
    private function rows(string $csvFile, CsvImportResult $result): array
    {
        $contents = is_file($csvFile) ? file_get_contents($csvFile) : false;
        if (false === $contents) {
            $result->addProblem(\sprintf('%s could not be read.', $csvFile));

            return [];
        }
        if (str_starts_with($contents, self::BOM)) {
            $contents = substr($contents, \strlen(self::BOM));
        }
        if (1 !== preg_match('//u', $contents)) {
            $result->addProblem(\sprintf('%s is not UTF-8 text. Save it again as "CSV UTF-8".', basename($csvFile)));

            return [];
        }

        $firstLine = strtok($contents, "\n");
        $firstLine = false === $firstLine ? '' : $firstLine;
        $counts = [',' => substr_count($firstLine, ','), ';' => substr_count($firstLine, ';'), "\t" => substr_count($firstLine, "\t")];
        arsort($counts);
        $separator = (string) array_key_first($counts);

        $stream = fopen('php://memory', 'w+');
        \assert(\is_resource($stream));
        fwrite($stream, $contents);
        rewind($stream);

        $header = fgetcsv($stream, null, $separator, '"', '');
        $columns = false === $header ? [] : array_flip(array_map(static fn (?string $name): string => strtolower(trim((string) $name)), $header));
        if (!isset($columns['key'], $columns['target'])) {
            $result->addProblem(\sprintf('%s needs a header row with at least the columns "key" and "target".', basename($csvFile)));
            fclose($stream);

            return [];
        }

        $rows = [];
        $line = 1;
        while (false !== ($row = fgetcsv($stream, null, $separator, '"', ''))) {
            ++$line;
            if ([null] === $row) {
                continue;
            }
            $rows[$line] = [
                'key' => trim((string) ($row[$columns['key']] ?? '')),
                'target' => (string) ($row[$columns['target']] ?? ''),
                'state' => isset($columns['state']) ? (string) ($row[$columns['state']] ?? '') : '',
            ];
        }
        fclose($stream);

        return $rows;
    }
}
