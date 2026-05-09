<?php

namespace App\Markdown\Section;

/**
 * Per-sekci konfigurace pro `GithubFetcher` + `CachedFetcher`. Jeden Config =
 * jedna sekce (např. "docs" nebo "wiki"). Drží GH coordinates, route prefix,
 * cache prefix a strategii pro sidebar label.
 */
final class Config
{
    public const LABEL_FILENAME = 'filename';
    public const LABEL_TITLE = 'title';

    public function __construct(
        /** Stable identifier — používá se jako prefix cache klíčů (např. `docs.list`). */
        public readonly string $name,
        /** GitHub repo coordinates. */
        public readonly string $owner,
        public readonly string $repo,
        public readonly string $branch,
        /** Adresář v repu, kde sekce žije (např. `docs` nebo `wiki`). */
        public readonly string $path,
        /** Route prefix pro internal MD link rewrite (např. `/docs`, `/wiki`). */
        public readonly string $internalRoutePrefix,
        /** Co dát do `Entry::label` — `filename` nebo `title` (frontmatter). */
        public readonly string $sidebarLabel = self::LABEL_FILENAME,
        /** Volitelný PAT pro GH API (vyšší rate limit). */
        public readonly ?string $token = null,
    ) {
    }
}
