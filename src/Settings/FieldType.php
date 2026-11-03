<?php

declare(strict_types=1);

namespace SightPress\Plugin\Core\Settings;

enum FieldType: string
{
    case Text = 'text';
    case TextArea = 'textarea';
    case Integer = 'integer';
    case Boolean = 'boolean';
    case Choice = 'choice';
    case Email = 'email';
    case Url = 'url';
}
