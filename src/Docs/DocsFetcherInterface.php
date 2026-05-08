<?php

namespace App\Docs;

interface DocsFetcherInterface
{
    /**
     * @return DocsListEntry[]
     */
    public function list(): array;

    /**
     * Returns null when slug doesn't exist (GitHub 404). For transport errors
     * implementations may fall back to last-known-good (see CachedDocsFetcher).
     */
    public function get(string $slug): ?DocFile;
}
