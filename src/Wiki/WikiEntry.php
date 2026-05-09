<?php

namespace App\Wiki;

final class WikiEntry
{
    public function __construct(
        public readonly string $slug,
        public readonly string $filename,
        public readonly string $title,
        public readonly string $lead,
        public readonly string $group,
        public readonly int $order,
        public readonly string $githubUrl,
    ) {
    }
}
