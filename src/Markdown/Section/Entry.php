<?php

namespace App\Markdown\Section;

/**
 * Lehký záznam pro list view (sidebar / index). Plný obsah (`body`) tahá až `Page`.
 *
 * `label` = co se zobrazí v sidebaru — buď filename (`architecture.md`) pro
 * docs sekci, nebo frontmatter title (`Příprava`) pro wiki sekci. Volbu
 * řídí `Config::sidebarLabel`.
 */
final class Entry
{
    public function __construct(
        public readonly string $slug,
        public readonly string $filename,
        public readonly string $label,
        public readonly string $githubUrl,
        public readonly string $group = '',
        public readonly int $order = PHP_INT_MAX,
    ) {
    }
}
