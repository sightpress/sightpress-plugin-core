<?php

declare(strict_types=1);

namespace SightPress\Plugin\Core\Value;

/**
 * A BCP 47 locale code such as "en-GB", "pt-BR", "es-419" or "he-IL".
 */
final readonly class Locale implements \Stringable
{
    /** The 13 launch UI locales (PLAN §3.1). */
    public const array LAUNCH = [
        'en-GB', 'en-US', 'pt-PT', 'pt-BR', 'es-ES', 'es-419', 'fr-FR',
        'fr-CA', 'it-IT', 'de-DE', 'de-CH', 'he-IL', 'ar-SA',
    ];

    private const array RTL_LANGUAGES = ['ar', 'he', 'fa', 'ur', 'ps', 'sd', 'ug', 'yi', 'dv'];

    /** The region each launch language's base catalogue is written for. */
    private const array BASE_REGIONS = [
        'en' => 'GB', 'pt' => 'PT', 'es' => 'ES', 'fr' => 'FR',
        'it' => 'IT', 'de' => 'DE', 'he' => 'IL', 'ar' => 'SA',
    ];

    private function __construct(private string $code)
    {
    }

    public static function fromString(string $code): self
    {
        if (1 !== preg_match('/^[a-z]{2,3}(-[A-Z][a-z]{3})?(-([A-Z]{2}|[0-9]{3}))?$/', $code)) {
            throw new \InvalidArgumentException(\sprintf('"%s" is not a valid BCP 47 locale code. Use a form such as "pt-BR".', $code));
        }

        return new self($code);
    }

    public function toString(): string
    {
        return $this->code;
    }

    public function language(): string
    {
        return explode('-', $this->code)[0];
    }

    public function region(): ?string
    {
        foreach (explode('-', $this->code) as $part) {
            if (1 === preg_match('/^([A-Z]{2}|[0-9]{3})$/', $part)) {
                return $part;
            }
        }

        return null;
    }

    public function direction(): string
    {
        return \in_array($this->language(), self::RTL_LANGUAGES, true) ? 'rtl' : 'ltr';
    }

    public function isRtl(): bool
    {
        return 'rtl' === $this->direction();
    }

    public function isLaunchLocale(): bool
    {
        return \in_array($this->code, self::LAUNCH, true);
    }

    /** The code in Symfony's form: "pt_BR". */
    public function toSymfony(): string
    {
        return str_replace('-', '_', $this->code);
    }

    /**
     * The catalogue suffix: a language's base region uses the bare language
     * ("pt" for pt-PT), every other region is an override ("pt_BR").
     */
    public function catalogue(): string
    {
        $region = $this->region();
        if (null === $region || (self::BASE_REGIONS[$this->language()] ?? null) === $region) {
            return $this->language();
        }

        return $this->toSymfony();
    }

    /** @return list<string> the 13 launch catalogue suffixes: en, en_US, pt, pt_BR, … */
    public static function launchCatalogues(): array
    {
        return array_map(static fn (string $code): string => self::fromString($code)->catalogue(), self::LAUNCH);
    }

    public function equals(self $other): bool
    {
        return $this->code === $other->code;
    }

    public function __toString(): string
    {
        return $this->code;
    }
}
