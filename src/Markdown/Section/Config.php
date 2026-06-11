<?php

namespace App\Markdown\Section;

/**
 * Per-sekci konfigurace pro `FilesystemFetcher`. Jeden Config = jedna sekce
 * (např. "docs" nebo "wiki"). Drží GitHub coordinates (jen pro blob/edit odkazy
 * do GH UI — obsah se čte z disku) + route prefix.
 */
final class Config
{
    public function __construct(
        /** GitHub repo coordinates — používané pouze pro skládání blob/edit URL. */
        public readonly string $owner,
        public readonly string $repo,
        public readonly string $branch,
        /** Adresář v repu, kde sekce žije (např. `docs` nebo `wiki`). */
        public readonly string $path,
        /** Route prefix pro internal MD link rewrite (např. `/docs`, `/wiki`). */
        public readonly string $internalRoutePrefix,
    ) {
    }
}
