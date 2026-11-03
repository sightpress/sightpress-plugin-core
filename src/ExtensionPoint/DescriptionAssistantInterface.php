<?php

declare(strict_types=1);

namespace SightPress\Plugin\Core\ExtensionPoint;

use SightPress\Plugin\Core\Dto\MediaRef;
use SightPress\Plugin\Core\Value\Locale;

/** Drafts a media description. Output is stored as `ai_draft` until a human reviews it. */
interface DescriptionAssistantInterface
{
    public function describe(MediaRef $media, Locale $locale): string;
}
