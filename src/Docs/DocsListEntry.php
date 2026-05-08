<?php

namespace App\Docs;

final class DocsListEntry
{
    public function __construct(
        public readonly string $slug,
        public readonly string $filename,
        public readonly string $label,
        public readonly string $githubUrl,
    ) {
    }
}
