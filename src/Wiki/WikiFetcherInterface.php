<?php

namespace App\Wiki;

interface WikiFetcherInterface
{
    /**
     * @return WikiEntry[]
     */
    public function list(): array;

    /**
     * Returns null when slug doesn't exist (GitHub 404). For transport errors
     * implementations may fall back to last-known-good (see CachedWikiFetcher).
     */
    public function get(string $slug): ?WikiPage;
}
