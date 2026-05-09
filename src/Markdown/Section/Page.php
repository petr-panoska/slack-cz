<?php

namespace App\Markdown\Section;

/**
 * Plně načtená MD stránka z `App\Markdown\Section\FetcherInterface::get()`.
 *
 * Frontmatter pole (`title`, `lead`, `quote`, `group`, `order`) jsou volitelná —
 * docs sekce je nemá, wiki sekce má všechna. Defaulty drží render bez bránění.
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
        public readonly string $lead = '',
        public readonly string $quote = '',
        public readonly string $group = '',
        public readonly int $order = PHP_INT_MAX,
    ) {
    }
}
