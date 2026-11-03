<?php

declare(strict_types=1);

namespace SightPress\Plugin\Core\ExtensionPoint;

use SightPress\Plugin\Core\Settings\SettingsSchema;

/** An admin page, menu item and optional settings form. The core renders the settings form. */
interface AdminSectionInterface
{
    public function id(): string;

    public function labelKey(): string;

    /** Route name of the section's landing page. */
    public function route(): string;

    /** Capability needed to see the section. */
    public function capability(): string;

    public function settings(): ?SettingsSchema;
}
