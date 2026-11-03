<?php

declare(strict_types=1);

namespace SightPress\Plugin\Core\ExtensionPoint;

use SightPress\Plugin\Core\Dto\ContentRef;

/** Drafts SEO fields. Output is stored as `ai_draft` until a human reviews it. */
interface SeoAssistantInterface
{
    /**
     * @return array{title: string, description: string}
     */
    public function suggest(ContentRef $content): array;
}
