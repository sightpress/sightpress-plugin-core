<?php

declare(strict_types=1);

namespace SightPress\Plugin\Core\Quality;

/**
 * The shared Twig lint for core, themes and every plugin (PLAN §3.5, plugins.md §5).
 * It reads templates as text, so it needs no Twig environment:
 *
 *  - raw-filter: "|raw", "{% apply raw %}" and "{% autoescape false %}" turn off escaping;
 *    only the core's sanitised body_html may be printed raw;
 *  - hard-coded-string: text and translatable attributes (alt, title, aria-label, …)
 *    must come from translations, never be written into the template;
 *  - inline-script: no inline <script>, on* event handler attributes or javascript: URLs
 *    (the strict content security policy refuses them);
 *  - third-party-resource: no scripts, stylesheets or fonts from other hosts;
 *  - positive-tabindex: tabindex above 0 breaks the focus order.
 *
 * There is no way to turn a rule off inside a template.
 */
final class TwigLinter
{
    /** Attributes whose value people read or hear, so it must be translated. */
    private const array TRANSLATABLE_ATTRIBUTES = [
        'alt', 'title', 'placeholder', 'aria-label', 'aria-description', 'aria-roledescription',
        'aria-valuetext', 'aria-placeholder', 'label', 'abbr', 'content',
    ];

    /** @return list<TwigFinding> */
    public function lintFile(string $path, ?string $displayName = null): array
    {
        $source = @file_get_contents($path);
        if (false === $source) {
            return [new TwigFinding($displayName ?? $path, 1, TwigFinding::SYNTAX, 'The template could not be read.')];
        }

        return $this->lintSource($source, $displayName ?? $path);
    }

    /**
     * Lints every *.twig file under the given directories.
     *
     * @param list<string> $directories
     *
     * @return array{files: int, findings: list<TwigFinding>}
     */
    public function lintDirectories(array $directories): array
    {
        $files = [];
        foreach ($directories as $directory) {
            if (!is_dir($directory)) {
                throw new \InvalidArgumentException(\sprintf('"%s" is not a directory.', $directory));
            }
            $iterator = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($directory, \FilesystemIterator::SKIP_DOTS));
            foreach ($iterator as $file) {
                if ($file instanceof \SplFileInfo && $file->isFile() && str_ends_with($file->getFilename(), '.twig')) {
                    $files[] = $file->getPathname();
                }
            }
        }
        sort($files);

        $findings = [];
        foreach ($files as $file) {
            array_push($findings, ...$this->lintFile($file));
        }

        return ['files' => \count($files), 'findings' => $findings];
    }

    /** @return list<TwigFinding> */
    public function lintSource(string $source, string $name = 'template'): array
    {
        $findings = [];
        $segments = $this->segments($source, $name, $findings);

        // The template with every Twig tag blanked out (newlines kept), for the HTML checks.
        $masked = $source;
        foreach ($segments as [$type, $offset, $length, $body]) {
            if ('text' !== $type) {
                $masked = substr_replace($masked, preg_replace('/[^\n]/', "\x01", substr($source, $offset, $length)) ?? '', $offset, $length);
            }
            if ('expression' === $type || 'block' === $type) {
                $this->checkEscaping($type, $body, $this->line($source, $offset), $name, $findings);
            }
        }

        $this->checkHtml($masked, $name, $findings);
        usort($findings, static fn (TwigFinding $a, TwigFinding $b): int => $a->line <=> $b->line);

        return $findings;
    }

    /**
     * Splits a template into text, {{ expression }}, {% block %} and {# comment #} segments.
     *
     * @param list<TwigFinding> $findings
     *
     * @return list<array{string, int, int, string}> type, offset, length, body (without delimiters)
     */
    private function segments(string $source, string $name, array &$findings): array
    {
        $segments = [];
        $length = \strlen($source);
        $position = 0;
        $verbatim = false;

        while ($position < $length) {
            if ($verbatim) {
                // Inside {% verbatim %}: everything is text until {% endverbatim %}.
                if (1 !== preg_match('/\{%[-~]?\s*end(?:verbatim|raw)\b/', $source, $m, \PREG_OFFSET_CAPTURE, $position)) {
                    $segments[] = ['text', $position, $length - $position, substr($source, $position)];
                    break;
                }
                $open = $m[0][1];
            } else {
                $open = strpos($source, '{', $position);
                while (false !== $open && !\in_array($source[$open + 1] ?? '', ['{', '%', '#'], true)) {
                    $open = strpos($source, '{', $open + 1);
                }
            }
            if (false === $open) {
                $segments[] = ['text', $position, $length - $position, substr($source, $position)];
                break;
            }
            if ($open > $position) {
                $segments[] = ['text', $position, $open - $position, substr($source, $position, $open - $position)];
            }

            $kind = $source[$open + 1];
            $close = match ($kind) {
                '{' => '}}',
                '%' => '%}',
                default => '#}',
            };
            $end = '#' === $kind ? strpos($source, $close, $open + 2) : $this->findClose($source, $open + 2, $close);
            if (false === $end) {
                $findings[] = new TwigFinding($name, $this->line($source, $open), TwigFinding::SYNTAX, \sprintf('This Twig tag is never closed with "%s".', $close));
                // The rest can't be read reliably, so it isn't checked further.
                $segments[] = ['comment', $open, $length - $open, ''];
                break;
            }

            $body = trim(substr($source, $open + 2, $end - $open - 2), " \t\n\r-~");
            $type = match ($kind) {
                '{' => 'expression',
                '%' => 'block',
                default => 'comment',
            };
            $segments[] = [$type, $open, $end + 2 - $open, $body];
            if ('block' === $type) {
                if (1 === preg_match('/^(verbatim|raw)\b/', $body)) {
                    $verbatim = true;
                } elseif (1 === preg_match('/^end(verbatim|raw)\b/', $body)) {
                    $verbatim = false;
                }
            }
            $position = $end + 2;
        }

        return $segments;
    }

    /** Finds the closing delimiter of a tag, skipping string literals. */
    private function findClose(string $source, int $from, string $close): int|false
    {
        $length = \strlen($source);
        for ($i = $from; $i < $length; ++$i) {
            $char = $source[$i];
            if ('"' === $char || "'" === $char) {
                $endQuote = $this->endOfString($source, $i);
                if (false === $endQuote) {
                    return false;
                }
                $i = $endQuote;
                continue;
            }
            if ($char === $close[0] && ($source[$i + 1] ?? '') === $close[1]) {
                return $i;
            }
        }

        return false;
    }

    private function endOfString(string $source, int $start): int|false
    {
        $quote = $source[$start];
        $length = \strlen($source);
        for ($i = $start + 1; $i < $length; ++$i) {
            if ('\\' === $source[$i]) {
                ++$i;
            } elseif ($source[$i] === $quote) {
                return $i;
            }
        }

        return false;
    }

    /** Blanks string literals so their contents can't look like code. */
    private function withoutStrings(string $code): string
    {
        return preg_replace_callback('/"(?:[^"\\\\]|\\\\.)*"|\'(?:[^\'\\\\]|\\\\.)*\'/s', static fn (array $m): string => str_repeat(' ', \strlen($m[0])), $code) ?? $code;
    }

    /** @param list<TwigFinding> $findings */
    private function checkEscaping(string $type, string $body, int $line, string $name, array &$findings): void
    {
        $code = $this->withoutStrings($body);
        if ('block' === $type) {
            if (1 === preg_match('/^autoescape\s+false\b/', $code)) {
                $findings[] = new TwigFinding($name, $line, TwigFinding::RAW, '"{% autoescape false %}" turns off escaping, so the output could run someone else\'s script. Keep escaping on; only the core\'s sanitised "body_html" may be printed with "|raw".');

                return;
            }
            if (1 === preg_match('/^(apply|filter)\b.*\braw\b/', $code)) {
                $findings[] = new TwigFinding($name, $line, TwigFinding::RAW, '"raw" in an "apply" block turns off escaping, so the output could run someone else\'s script. Only the core\'s sanitised "body_html" may be printed with "|raw".');

                return;
            }
        }

        if (false === preg_match_all('/\|\s*raw\b/', $code, $matches, \PREG_OFFSET_CAPTURE)) {
            return; // @codeCoverageIgnore
        }
        foreach ($matches[0] as [, $offset]) {
            $before = rtrim(substr($code, 0, $offset));
            if (1 === preg_match('/(?:^|[\s(.])body_html$/', $before)) {
                continue;
            }
            $findings[] = new TwigFinding($name, $line + substr_count($body, "\n", 0, $offset), TwigFinding::RAW, '"|raw" turns off escaping, so this output could run someone else\'s script. Only the core\'s sanitised "body_html" may use it; print the value escaped instead.');
        }
    }

    /** @param list<TwigFinding> $findings */
    private function checkHtml(string $masked, string $name, array &$findings): void
    {
        $blank = static fn (string $text): string => preg_replace('/[^\n]/', ' ', $text) ?? $text;
        $html = preg_replace_callback('/<!--.*?-->|<!\[CDATA\[.*?]]>|<![^>]*>/s', static fn (array $m): string => $blank($m[0]), $masked) ?? $masked;

        // Script and style elements: their contents are code, not text.
        $html = preg_replace_callback('/<(script|style)\b([^>]*)>(.*?)<\/\1\s*>/is', function (array $m) use ($html, $name, &$findings, $blank): string {
            [[$element, $offset], [$tag], [$attributeSource], [$content, $contentOffset]] = $m;
            $attributes = $this->attributes($attributeSource);
            if ('script' === strtolower($tag) && !isset($attributes['src']) && '' !== trim(str_replace("\x01", 'x', $content))
                && 'application/ld+json' !== strtolower($attributes['type'] ?? '')) {
                $findings[] = new TwigFinding($name, $this->line($html, $offset), TwigFinding::INLINE_SCRIPT, 'An inline <script> breaks the strict content security policy and only works with JavaScript on. Make it a Stimulus controller loaded through AssetMapper, enhancing markup that already works.');
            }

            return substr_replace($element, $blank($content), $contentOffset - $offset, \strlen($content));
        }, $html, -1, $count, \PREG_OFFSET_CAPTURE) ?? $html;

        // Tags: check their attributes, then blank them so only text is left.
        $html = preg_replace_callback('/<\/?([a-zA-Z][a-zA-Z0-9-]*)((?:[^>"\']|"[^"]*"|\'[^\']*\')*)>/', function (array $m) use ($html, $name, &$findings, $blank): string {
            [[$element, $offset], [$tag], [$attributeSource]] = $m;
            $this->checkTag(strtolower($tag), $this->attributes($attributeSource), $this->line($html, $offset), $name, $findings);

            return $blank($element);
        }, $html, -1, $count, \PREG_OFFSET_CAPTURE) ?? $html;

        // What's left is text that people read: it must not be hard-coded.
        $text = preg_replace('/&(?:[a-zA-Z][a-zA-Z0-9]*|#[0-9]+|#x[0-9a-fA-F]+);/', ' ', $html) ?? $html;
        if (false === preg_match_all('/[^\s\x01<>]*\p{L}[^\x01<>\n]*/u', $text, $matches, \PREG_OFFSET_CAPTURE)) {
            return; // @codeCoverageIgnore
        }
        foreach ($matches[0] as [$found, $offset]) {
            $findings[] = new TwigFinding($name, $this->line($text, $offset), TwigFinding::HARD_CODED_STRING, \sprintf(
                '"%s" is hard-coded text, so it can\'t be translated. Use a translation key, for example {{ \'my_plugin.label\'|trans }}.',
                $this->shorten(trim($found)),
            ));
        }
    }

    /**
     * @param array<string, string|null> $attributes
     * @param list<TwigFinding>          $findings
     */
    private function checkTag(string $tag, array $attributes, int $line, string $name, array &$findings): void
    {
        foreach ($attributes as $attribute => $value) {
            if (str_starts_with($attribute, 'on')) {
                $findings[] = new TwigFinding($name, $line, TwigFinding::INLINE_SCRIPT, \sprintf('The "%s" attribute is inline JavaScript, which the strict content security policy refuses. Use a Stimulus action (data-action) instead.', $attribute));
            }
            if (null === $value) {
                continue;
            }
            if (\in_array($attribute, ['href', 'src', 'action', 'formaction'], true) && 1 === preg_match('/^\s*javascript:/i', $value)) {
                $findings[] = new TwigFinding($name, $line, TwigFinding::INLINE_SCRIPT, \sprintf('A "javascript:" URL in "%s" is inline JavaScript, which the strict content security policy refuses. Use a real URL or a button with a Stimulus action.', $attribute));
            }
            if ('tabindex' === $attribute && 1 === preg_match('/^\s*\+?[1-9][0-9]*\s*$/', $value)) {
                $findings[] = new TwigFinding($name, $line, TwigFinding::POSITIVE_TABINDEX, \sprintf('tabindex="%s" moves this element out of the reading order for keyboard users. Use tabindex="0" or "-1", or put the markup in the right order.', trim($value)));
            }
            if (\in_array($attribute, self::TRANSLATABLE_ATTRIBUTES, true) && ('content' !== $attribute || 'meta' === $tag && $this->isTextMeta($attributes))
                && 1 === preg_match('/\p{L}/u', str_replace("\x01", '', $value))) {
                $findings[] = new TwigFinding($name, $line, TwigFinding::HARD_CODED_STRING, \sprintf('The "%s" attribute has hard-coded text "%s", so it can\'t be translated. Use a translation key.', $attribute, $this->shorten(trim(str_replace("\x01", '', $value)))));
            }
            if ('value' === $attribute && 'input' === $tag && \in_array(strtolower($attributes['type'] ?? ''), ['submit', 'button', 'reset'], true)
                && 1 === preg_match('/\p{L}/u', str_replace("\x01", '', $value))) {
                $findings[] = new TwigFinding($name, $line, TwigFinding::HARD_CODED_STRING, \sprintf('The button text "%s" is hard-coded, so it can\'t be translated. Use a translation key.', $this->shorten(trim(str_replace("\x01", '', $value)))));
            }
        }

        $url = match ($tag) {
            'script' => $attributes['src'] ?? null,
            'link' => $attributes['href'] ?? null,
            default => null,
        };
        if (null !== $url && 1 === preg_match('#^\s*(?:https?:)?//#i', $url)) {
            $findings[] = new TwigFinding($name, $line, TwigFinding::THIRD_PARTY, \sprintf('"%s" loads a %s from another host, which can track visitors and breaks the strict content security policy. Ship the file with the plugin or theme through AssetMapper.', trim($url), 'script' === $tag ? 'script' : 'stylesheet or font'));
        }
    }

    /** @param array<string, string|null> $attributes */
    private function isTextMeta(array $attributes): bool
    {
        $key = strtolower($attributes['name'] ?? $attributes['property'] ?? '');

        return \in_array($key, ['description', 'og:title', 'og:description', 'twitter:title', 'twitter:description', 'og:image:alt', 'application-name', 'apple-mobile-web-app-title'], true);
    }

    /** @return array<string, string|null> lower-cased attribute names; null for attributes without a value */
    private function attributes(string $source): array
    {
        $attributes = [];
        preg_match_all('/([^\s"\'<>\/=\x01]+)(?:\s*=\s*(?:"([^"]*)"|\'([^\']*)\'|([^\s"\'=<>`]+)))?/', $source, $matches, \PREG_SET_ORDER | \PREG_UNMATCHED_AS_NULL);
        foreach ($matches as $match) {
            $attributes[strtolower((string) $match[1])] = $match[2] ?? $match[3] ?? $match[4] ?? null;
        }

        return $attributes;
    }

    private function line(string $source, int $offset): int
    {
        return substr_count($source, "\n", 0, $offset) + 1;
    }

    private function shorten(string $text): string
    {
        return mb_strlen($text) > 60 ? mb_substr($text, 0, 57).'…' : $text;
    }
}
