<?php

declare(strict_types=1);

namespace SightPress\Plugin\Core\Dto;

use SightPress\Plugin\Core\Value\Locale;

/** Transactional mail someone asked for: confirmations, form copies to configured recipients. */
final readonly class TransactionalMail
{
    /**
     * @param array<string, mixed> $context
     */
    public function __construct(
        public string $to,
        public Locale $locale,
        public string $template,
        public string $subjectKey,
        public array $context = [],
    ) {
    }
}
