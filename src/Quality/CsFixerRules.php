<?php

declare(strict_types=1);

namespace SightPress\Plugin\Core\Quality;

use PhpCsFixer\Config;
use PhpCsFixer\Finder;

/**
 * The shared PHP-CS-Fixer ruleset. Every repository's .php-cs-fixer.dist.php
 * calls CsFixerRules::config($finder).
 */
final class CsFixerRules
{
    /** @return array<string, mixed> */
    public static function rules(): array
    {
        return [
            '@Symfony' => true,
            '@Symfony:risky' => true,
            '@PHP8x4Migration' => true,
            'declare_strict_types' => true,
            'strict_param' => true,
            'array_syntax' => ['syntax' => 'short'],
            'ordered_imports' => ['sort_algorithm' => 'alpha'],
            'global_namespace_import' => ['import_classes' => false, 'import_constants' => false, 'import_functions' => false],
            'phpdoc_to_comment' => false,
            'phpdoc_summary' => false,
            'single_line_throw' => false,
            'yoda_style' => ['equal' => true, 'identical' => true, 'less_and_greater' => false],
            'native_function_invocation' => ['include' => ['@compiler_optimized'], 'scope' => 'namespaced'],
        ];
    }

    public static function config(Finder $finder): Config
    {
        /** @var array<string, array<string, mixed>|bool> $rules */
        $rules = self::rules();

        return new Config()
            ->setRiskyAllowed(true)
            ->setRules($rules)
            ->setFinder($finder);
    }
}
