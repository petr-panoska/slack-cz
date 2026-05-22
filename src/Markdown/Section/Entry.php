<?php

namespace App\Markdown\Section;

/**
 * Lehký záznam pro list view (sidebar / index). Plný obsah (`body`) tahá až `Page`.
 *
 * `label` = první H1 souboru (fallback slug).
 * `group` = první H1 ze `README.md` v jeho folderu (fallback folder name bez `^\d+-`).
 */
final class Entry
{
    public function __construct(
        public readonly string $slug,
        public readonly string $filename,
        public readonly string $label,
        public readonly string $githubUrl,
        public readonly string $group = '',
    ) {
    }
}
