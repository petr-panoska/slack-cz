<?php

namespace App\Markdown\Section;

use App\Markdown\MarkdownRenderer;

/**
 * Fetcher MD souborů z lokálního filesystému (deployovaný checkout).
 *
 * Soubory sekce žijí přímo v repu (`wiki/`, `docs/`) a deploy je `git pull`-ne
 * na server, takže není důvod je za runtime tahat přes HTTP z GitHubu — čteme
 * je rovnou z disku. Blob/edit odkazy do GitHub UI se pořád skládají z `Config`
 * (owner/repo/branch/path), žádný síťový request k tomu netřeba.
 *
 * Konvence (žádný frontmatter, žádné metadata pole):
 *  - Pořadí v sidebaru = abecední podle relativního path (folder má `^\d+-`
 *    prefix, file taky). `01-foo/02-bar.md` < `01-foo/03-baz.md` < `02-x/...`
 *  - Slug v URL = filename bez `^\d+-` prefixu a `.md` přípony.
 *    `01-pouzivani-highline/02-bezpecnost.md` → `/wiki/bezpecnost`.
 *  - Label v sidebaru = první H1 v souboru, fallback na slug.
 *  - Group v sidebaru = H2 v root `README.md`, ke kterému folder patří
 *    (mapping podle linků pod tím H2). Fallback: folder name bez prefixu.
 *  - Sekční root `README.md` = index, list ho přeskakuje.
 *
 * Slug musí být unikátní napříč subtree (kolize = první vyhraje).
 */
final class FilesystemFetcher implements FetcherInterface
{
    /** @var array<string,string>|null Mapa slug → relativní path (od `Config::path`). */
    private ?array $slugMap = null;

    /** @var array<string,string>|null Mapa folder rel path → group label (z root README H2). */
    private ?array $folderLabels = null;

    /** Absolutní cesta k adresáři sekce (`%kernel.project_dir%/{Config::path}`). */
    private readonly string $baseDir;

    public function __construct(
        private readonly Config $config,
        string $projectDir,
    ) {
        $this->baseDir = rtrim($projectDir, '/') . '/' . trim($this->config->path, '/');
    }

    public function list(): array
    {
        // Sort by relative path. Folder + file mají `^\d+-` prefix, takže
        // lexikografické řazení dá správné pořadí napříč subfoldery.
        $slugMap = $this->slugMap();
        uasort($slugMap, fn (string $a, string $b) => strcmp($a, $b));

        $folderLabels = $this->folderLabels();
        $entries = [];

        foreach ($slugMap as $slug => $relPath) {
            // Pro label čteme jen hlavičku po první H1 — wiki MD můžou mít stovky
            // KB inline base64 obrázků a list() běží na každém renderu, takže
            // celý soubor sem tahat nechceme (body se čte až v get()).
            $title = $this->titleByPath($relPath);
            $folder = $this->folderOf($relPath);
            $entries[] = new Entry(
                slug: $slug,
                filename: basename($relPath),
                label: $title !== '' ? $title : $slug,
                githubUrl: $this->buildBlobUrl($relPath),
                group: $folderLabels[$folder] ?? $this->fallbackFolderLabel($folder),
            );
        }

        return $entries;
    }

    public function get(string $slug): ?Page
    {
        if (!self::isValidSlug($slug)) {
            return null;
        }
        // README je root-level index, mimo slugMap.
        $relPath = strcasecmp($slug, 'README') === 0
            ? 'README.md'
            : ($this->slugMap()[$slug] ?? null);
        if ($relPath === null) {
            return null;
        }
        return $this->fetchByPath($slug, $relPath);
    }

    private function fetchByPath(string $slug, string $relPath): ?Page
    {
        $absPath = $this->baseDir . '/' . $relPath;
        if (!is_file($absPath)) {
            return null;
        }

        $body = @file_get_contents($absPath);
        if ($body === false) {
            throw new \RuntimeException(sprintf('Failed reading %s', $absPath));
        }

        return new Page(
            slug: $slug,
            filename: basename($relPath),
            body: $body,
            githubUrl: $this->buildBlobUrl($relPath),
            githubEditUrl: $this->buildEditUrl($relPath),
            title: MarkdownRenderer::extractTitle($body) ?? '',
        );
    }

    /**
     * Vytáhne H1 titulek bez načtení celého souboru — streamuje řádky a zastaví
     * na první H1. Sémanticky shodné s `MarkdownRenderer::extractTitle()` (první
     * `^#\s+` v dokumentu, H2+ nematchují), ale nečte MB-velké base64 přílohy.
     */
    private function titleByPath(string $relPath): string
    {
        $handle = @fopen($this->baseDir . '/' . $relPath, 'rb');
        if ($handle === false) {
            return '';
        }

        $title = '';
        while (($line = fgets($handle)) !== false) {
            if (preg_match('/^#\s+(.+?)\s*$/', $line, $m) === 1) {
                $title = trim($m[1]);
                break;
            }
        }
        fclose($handle);

        return $title;
    }

    /**
     * @return array<string,string>
     */
    private function slugMap(): array
    {
        if ($this->slugMap !== null) {
            return $this->slugMap;
        }

        $this->buildMaps();
        return $this->slugMap;
    }

    /**
     * @return array<string,string> Folder rel path → group label.
     */
    private function folderLabels(): array
    {
        if ($this->folderLabels !== null) {
            return $this->folderLabels;
        }

        $readme = $this->fetchByPath('README', 'README.md');
        if ($readme === null) {
            return $this->folderLabels = [];
        }

        // Reálné foldery z slugMap — externí URL v README s `.md` v cestě
        // tím přefiltrujeme bez nutnosti strict regexu.
        $validFolders = [];
        foreach ($this->slugMap() as $relPath) {
            $folder = $this->folderOf($relPath);
            if ($folder !== '') {
                $validFolders[$folder] = true;
            }
        }

        // Parse: každé `## Heading` zaštiťuje skupinu, pod ním jsou linky
        // `[text](folder/file.md)` — folder mapujeme na to H2.
        $labels = [];
        $currentH2 = '';
        foreach (preg_split('/\R/u', $readme->body) as $line) {
            if (preg_match('/^##\s+(.+?)\s*$/u', $line, $m) === 1) {
                $currentH2 = trim($m[1]);
                continue;
            }
            if ($currentH2 === '') {
                continue;
            }
            if (preg_match_all('/\(([^)\/\s]+)\/[^)\s]+\.md\)/u', $line, $m) > 0) {
                foreach ($m[1] as $folder) {
                    if (isset($validFolders[$folder]) && !isset($labels[$folder])) {
                        $labels[$folder] = $currentH2;
                    }
                }
            }
        }

        return $this->folderLabels = $labels;
    }

    private function buildMaps(): void
    {
        $slugMap = [];

        foreach ($this->mdFilesRelative() as $rel) {
            // README na jakékoli úrovni přeskakujeme — sekční root = index,
            // ostatní jsou bezpředmětné (group label tahá root README parsing).
            if ($rel === 'README.md' || str_ends_with($rel, '/README.md')) {
                continue;
            }

            $slug = self::slugFromFilename(basename($rel));
            if (!self::isValidSlug($slug)) {
                continue;
            }
            $slugMap[$slug] = $rel;
        }

        $this->slugMap = $slugMap;
    }

    /**
     * Rekurzivně najde všechny `.md` soubory pod `baseDir` a vrátí jejich cesty
     * relativní k `baseDir` (forward slashes). Pořadí není garantované — `list()`
     * si je stejně lexikograficky řadí.
     *
     * @return list<string>
     */
    private function mdFilesRelative(): array
    {
        if (!is_dir($this->baseDir)) {
            return [];
        }

        // CATCH_GET_CHILD: nečitelný podadresář se přeskočí místo vyhození
        // UnexpectedValueException — sekce se vyrendruje s tím, co jde přečíst,
        // místo 500 (cache/LKG fallback tu už není, co by to odchytil).
        $iterator = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($this->baseDir, \FilesystemIterator::SKIP_DOTS),
            \RecursiveIteratorIterator::LEAVES_ONLY,
            \RecursiveIteratorIterator::CATCH_GET_CHILD,
        );

        $prefix = rtrim($this->baseDir, '/') . '/';
        $files = [];
        foreach ($iterator as $file) {
            /** @var \SplFileInfo $file */
            $path = str_replace(\DIRECTORY_SEPARATOR, '/', $file->getPathname());
            if (!$file->isFile() || !str_ends_with($path, '.md')) {
                continue;
            }
            if (str_starts_with($path, $prefix)) {
                $files[] = substr($path, strlen($prefix));
            }
        }

        return $files;
    }

    private function fallbackFolderLabel(string $folder): string
    {
        if ($folder === '') {
            return '';
        }
        $name = basename($folder);
        return preg_replace('/^\d+-/', '', $name) ?? $name;
    }

    private function folderOf(string $relPath): string
    {
        $dir = dirname($relPath);
        return $dir === '.' ? '' : $dir;
    }

    private function buildBlobUrl(string $relPath): string
    {
        return sprintf(
            'https://github.com/%s/%s/blob/%s/%s/%s',
            $this->config->owner,
            $this->config->repo,
            $this->config->branch,
            $this->config->path,
            $relPath,
        );
    }

    private function buildEditUrl(string $relPath): string
    {
        return sprintf(
            'https://github.com/%s/%s/edit/%s/%s/%s',
            $this->config->owner,
            $this->config->repo,
            $this->config->branch,
            $this->config->path,
            $relPath,
        );
    }

    public static function slugFromFilename(string $filename): string
    {
        $base = basename($filename, '.md');
        return preg_replace('/^\d+-/', '', $base) ?? $base;
    }

    private static function isValidSlug(string $slug): bool
    {
        return $slug !== '' && preg_match('/^[A-Za-z0-9][A-Za-z0-9._-]*$/', $slug) === 1;
    }
}
