<?php

declare(strict_types=1);

namespace SightPress\Plugin\Core\ExtensionPoint;

/** Critiques a theme's design tokens. Output is advice, stored as `ai_draft`. */
interface ThemeCritiqueInterface
{
    /**
     * @param array<string, string> $tokens design token name => value
     *
     * @return list<string>
     */
    public function critique(array $tokens): array;
}
