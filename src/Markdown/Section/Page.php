<?php

namespace App\Markdown\Section;

/**
 * Plně načtená MD stránka z `App\Markdown\Section\FetcherInterface::get()`.
 *
 * `title` = první H1 v body (fallback prázdný řetězec). Body si svůj H1 nese
 * sám — render ho ukáže přímo, žádné stripování.
 */
final class Page
{
    public function __construct(
        public readonly string $slug,
        public readonly string $filename,
        public readonly string $body,
        public readonly string $githubUrl,
        public readonly string $githubEditUrl,
        public readonly string $title = '',
    ) {
    }
}
