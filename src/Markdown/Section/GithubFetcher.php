<?php

namespace App\Markdown\Section;

use App\Markdown\MarkdownRenderer;
use Symfony\Contracts\HttpClient\Exception\ExceptionInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;

/**
 * Fetcher MD souborů z GitHub repa. Používá:
 *  - git trees API (`?recursive=1`) pro list — jeden call, podporuje subfoldery
 *  - raw.githubusercontent.com pro content — CDN, mimo API rate limit
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
final class GithubFetcher implements FetcherInterface
{
    /** @var array<string,string>|null Mapa slug → relativní path (od `Config::path`). */
    private ?array $slugMap = null;

    /** @var array<string,string>|null Mapa folder rel path → group label (z root README H2). */
    private ?array $folderLabels = null;

    public function __construct(
        private readonly HttpClientInterface $httpClient,
        private readonly Config $config,
    ) {
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
            $page = $this->fetchByPath($slug, $relPath);
            if ($page === null) {
                continue;
            }
            $folder = $this->folderOf($relPath);
            $entries[] = new Entry(
                slug: $page->slug,
                filename: basename($relPath),
                label: $page->title !== '' ? $page->title : $page->slug,
                githubUrl: $page->githubUrl,
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
        $url = sprintf(
            'https://raw.githubusercontent.com/%s/%s/%s/%s/%s',
            rawurlencode($this->config->owner),
            rawurlencode($this->config->repo),
            rawurlencode($this->config->branch),
            $this->config->path,
            implode('/', array_map('rawurlencode', explode('/', $relPath))),
        );

        $response = $this->httpClient->request('GET', $url, [
            'headers' => $this->headers(),
        ]);

        try {
            $status = $response->getStatusCode();
        } catch (ExceptionInterface $e) {
            throw new \RuntimeException(sprintf('Failed reading %s: %s', $url, $e->getMessage()), 0, $e);
        }

        if ($status === 404) {
            return null;
        }
        if ($status !== 200) {
            throw new \RuntimeException(sprintf('GitHub raw returned HTTP %d for %s', $status, $url));
        }

        $body = $response->getContent(false);

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
        $tree = $this->fetchTree();
        $slugMap = [];
        $prefix = rtrim($this->config->path, '/') . '/';

        foreach ($tree as $node) {
            if (($node['type'] ?? null) !== 'blob') {
                continue;
            }
            $path = $node['path'] ?? '';
            if (!str_starts_with($path, $prefix) || !str_ends_with($path, '.md')) {
                continue;
            }
            $rel = substr($path, strlen($prefix));
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

    /**
     * @return list<array<string,mixed>>
     */
    private function fetchTree(): array
    {
        $url = sprintf(
            'https://api.github.com/repos/%s/%s/git/trees/%s?recursive=1',
            rawurlencode($this->config->owner),
            rawurlencode($this->config->repo),
            rawurlencode($this->config->branch),
        );

        $response = $this->httpClient->request('GET', $url, [
            'headers' => $this->headers(['Accept' => 'application/vnd.github+json']),
        ]);

        $status = $response->getStatusCode();
        if ($status !== 200) {
            throw new \RuntimeException(sprintf('GitHub git trees API returned HTTP %d for %s', $status, $url));
        }

        $data = $response->toArray(false);
        return $data['tree'] ?? [];
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

    /**
     * @param array<string,string> $extra
     * @return array<string,string>
     */
    private function headers(array $extra = []): array
    {
        $base = [
            'User-Agent' => 'slack-cz-md-section/1.0',
        ];
        if ($this->config->token !== null && $this->config->token !== '') {
            $base['Authorization'] = 'Bearer ' . $this->config->token;
        }
        return $base + $extra;
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
