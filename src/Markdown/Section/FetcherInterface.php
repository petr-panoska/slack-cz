<?php

namespace App\Markdown\Section;

interface FetcherInterface
{
    /**
     * @return Entry[] entries v deterministickém pořadí (lexikografický sort
     *                relativního path — `NN-` prefix ve folderu i filename určuje
     *                pozici). README je vždy vyřazený — slouží jako index a tahá
     *                se přes `get('README')` separátně.
     */
    public function list(): array;

    /**
     * Returns null when slug doesn't exist (GitHub 404). Pro transport errors
     * (network / 5xx / rate limit) implementace mohou fallnout na last-known-good
     * (viz `CachedFetcher`).
     */
    public function get(string $slug): ?Page;
}
