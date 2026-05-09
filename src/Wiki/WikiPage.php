<?php

namespace App\Wiki;

final class WikiPage
{
    public function __construct(
        public readonly string $slug,
        public readonly string $filename,
        public readonly string $title,
        public readonly string $lead,
        public readonly string $quote,
        public readonly string $group,
        public readonly int $order,
        public readonly string $body,
        public readonly string $githubUrl,
        public readonly string $githubEditUrl,
    ) {
    }
}
