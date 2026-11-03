<?php

declare(strict_types=1);

namespace SightPress\Plugin\Core\ExtensionPoint;

use SightPress\Plugin\Core\Value\Locale;

/** A section added to one of the five legal pages. Plugins add sections, never remove core ones. */
interface LegalSectionInterface
{
    /** One of: about-us, contact, accessibility-statement, privacy-policy, terms-of-use. */
    public function role(): string;

    public function position(): int;

    public function markdown(Locale $locale): string;
}
