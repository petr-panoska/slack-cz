<?php

namespace App\Docs;

final class DocFile
{
    public function __construct(
        public readonly string $slug,
        public readonly string $filename,
        public readonly string $label,
        public readonly string $body,
        public readonly string $githubUrl,
        public readonly string $githubEditUrl,
    ) {
    }
}
