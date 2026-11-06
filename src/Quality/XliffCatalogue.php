<?php

declare(strict_types=1);

namespace SightPress\Plugin\Core\Quality;

/**
 * One XLIFF 1.2 catalogue file, read and changed through the DOM so that
 * saving it changes only the units that were touched (whitespace, comments
 * and attribute order are kept).
 */
final class XliffCatalogue
{
    private const string NS = 'urn:oasis:names:tc:xliff:document:1.2';

    /** The XLIFF 1.2 values of a target's state attribute. */
    public const array STATES = [
        'new', 'needs-translation', 'needs-adaptation', 'needs-l10n',
        'needs-review-translation', 'needs-review-adaptation', 'needs-review-l10n',
        'translated', 'signed-off', 'final',
    ];

    /** @var array<string, \DOMElement> */
    private array $units = [];

    private function __construct(private readonly \DOMDocument $dom, private readonly \DOMElement $body)
    {
        foreach ($dom->getElementsByTagName('trans-unit') as $unit) {
            $this->units[$unit->getAttribute('id')] = $unit;
        }
    }

    public static function load(string $file): self
    {
        $dom = new \DOMDocument();
        $dom->preserveWhiteSpace = true;
        $previous = libxml_use_internal_errors(true);
        $contents = is_file($file) ? file_get_contents($file) : false;
        $loaded = false !== $contents && $dom->loadXML($contents, \LIBXML_NONET);
        libxml_clear_errors();
        libxml_use_internal_errors($previous);

        $body = $loaded ? $dom->getElementsByTagName('body')->item(0) : null;
        if (null === $body) {
            throw new \InvalidArgumentException(\sprintf('%s is not a readable XLIFF 1.2 catalogue.', basename($file)));
        }

        return new self($dom, $body);
    }

    /** An empty catalogue for a locale that has none yet, written like the core catalogues. */
    public static function create(string $domain, string $targetLanguage): self
    {
        return self::fromString(\sprintf(
            "<?xml version=\"1.0\" encoding=\"utf-8\"?>\n<xliff xmlns=\"%s\" version=\"1.2\">\n  <file source-language=\"en-GB\" target-language=\"%s\" datatype=\"plaintext\" original=\"%s\">\n    <body>\n    </body>\n  </file>\n</xliff>\n",
            self::NS,
            htmlspecialchars($targetLanguage, \ENT_XML1 | \ENT_QUOTES),
            htmlspecialchars($domain, \ENT_XML1 | \ENT_QUOTES),
        ));
    }

    private static function fromString(string $xml): self
    {
        $dom = new \DOMDocument();
        $dom->preserveWhiteSpace = true;
        $dom->loadXML($xml, \LIBXML_NONET);
        $body = $dom->getElementsByTagName('body')->item(0);
        \assert(null !== $body);

        return new self($dom, $body);
    }

    /** @return list<string> the keys, in file order */
    public function keys(): array
    {
        return array_map(strval(...), array_keys($this->units));
    }

    public function has(string $key): bool
    {
        return isset($this->units[$key]);
    }

    public function target(string $key): ?string
    {
        $target = $this->targetElement($key);

        return $target?->textContent;
    }

    public function state(string $key): string
    {
        return $this->targetElement($key)?->getAttribute('state') ?? '';
    }

    public function note(string $key): string
    {
        $note = isset($this->units[$key]) ? $this->units[$key]->getElementsByTagName('note')->item(0) : null;

        return null === $note ? '' : trim($note->textContent);
    }

    /** Sets a unit's target and state, adding the unit (with its English source) or its target when missing. */
    public function set(string $key, string $source, string $target, string $state): void
    {
        $unit = $this->units[$key] ?? $this->addUnit($key, $source);
        $element = $this->targetElement($key) ?? $this->addTarget($unit);
        $element->textContent = $target;
        $element->setAttribute('state', $state);
    }

    public function toXml(): string
    {
        return (string) $this->dom->saveXML();
    }

    private function targetElement(string $key): ?\DOMElement
    {
        return isset($this->units[$key]) ? $this->units[$key]->getElementsByTagName('target')->item(0) : null;
    }

    private function addUnit(string $key, string $source): \DOMElement
    {
        $namespace = $this->body->namespaceURI;
        $unit = $this->dom->createElementNS($namespace, 'trans-unit');
        $unit->setAttribute('id', $key);
        $unit->setAttribute('resname', $key);
        $sourceElement = $this->dom->createElementNS($namespace, 'source');
        $sourceElement->textContent = $source;
        $unit->append("\n        ", $sourceElement, "\n      ");

        // Keep the indentation of the core catalogues: units at six spaces, before the body's closing indent.
        $last = $this->body->lastChild;
        if ($last instanceof \DOMText && '' === trim($last->data)) {
            $this->body->insertBefore($this->dom->createTextNode("\n      "), $last);
            $this->body->insertBefore($unit, $last);
        } else {
            $this->body->appendChild($unit);
        }

        return $this->units[$key] = $unit;
    }

    private function addTarget(\DOMElement $unit): \DOMElement
    {
        $target = $this->dom->createElementNS($unit->namespaceURI, 'target');
        $source = $unit->getElementsByTagName('source')->item(0);
        if (null === $source) {
            $unit->appendChild($target);

            return $target;
        }
        $indent = $source->previousSibling instanceof \DOMText ? $source->previousSibling->data : '';
        $source->after($indent, $target);

        return $target;
    }
}
