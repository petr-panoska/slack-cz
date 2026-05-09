<?php

namespace App\Markdown\Section;

use Symfony\Component\Yaml\Yaml;
use Symfony\Contracts\HttpClient\Exception\ExceptionInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;

/**
 * Fetcher MD souborů z GitHub repa. Používá:
 *  - git trees API (`?recursive=1`) pro list — jeden call, podporuje subfoldery
 *  - raw.githubusercontent.com pro content — CDN, mimo API rate limit
 *
 * Slug = filename bez `.md` a musí být unikátní napříč subtree (kolize = první vyhraje).
 * README.md je root-level index — nečte ho list(), get('README') ho pulluje speciálně.
 */
final class GithubFetcher implements FetcherInterface
{
    /** @var array<string,string>|null Mapa slug → relativní path (od `Config::path`). */
    private ?array $slugMap = null;

    public function __construct(
        private readonly HttpClientInterface $httpClient,
        private readonly Config $config,
    ) {
    }

    public function list(): array
    {
        $entries = [];
        foreach ($this->slugMap() as $slug => $relPath) {
            $page = $this->fetchByPath($slug, $relPath);
            if ($page === null) {
                continue;
            }
            $entries[] = new Entry(
                slug: $page->slug,
                filename: $page->filename,
                label: $this->config->sidebarLabel === Config::LABEL_TITLE && $page->title !== ''
                    ? $page->title
                    : $page->filename,
                githubUrl: $page->githubUrl,
                group: $page->group,
                order: $page->order,
            );
        }

        usort($entries, function (Entry $a, Entry $b) {
            $cmp = $a->order <=> $b->order;
            return $cmp !== 0 ? $cmp : strcmp($a->filename, $b->filename);
        });

        return $entries;
    }

    public function get(string $slug): ?Page
    {
        if (!self::isValidSlug($slug)) {
            return null;
        }
        // README je root-level index, mimo slugMap (může být v subfolderech kapitol jiný README).
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

        return $this->parsePage($slug, basename($relPath), $relPath, $response->getContent(false));
    }

    /**
     * @return array<string,string>
     */
    private function slugMap(): array
    {
        if ($this->slugMap !== null) {
            return $this->slugMap;
        }

        $tree = $this->fetchTree();
        $map = [];
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
            // README na jakékoli úrovni přeskakujeme — je to index, ne kapitola.
            if ($rel === 'README.md' || str_ends_with($rel, '/README.md')) {
                continue;
            }
            $slug = basename($rel, '.md');
            if (!self::isValidSlug($slug)) {
                continue;
            }
            $map[$slug] = $rel;
        }
        return $this->slugMap = $map;
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

    private function parsePage(string $slug, string $filename, string $relPath, string $raw): Page
    {
        $title = '';
        $lead = '';
        $quote = '';
        $group = '';
        $order = PHP_INT_MAX;
        $body = $raw;

        if (preg_match('/^---\R(.*?)\R---\R(.*)$/us', $raw, $m) === 1) {
            try {
                $fm = Yaml::parse($m[1]) ?: [];
            } catch (\Throwable) {
                $fm = [];
            }
            if (is_array($fm)) {
                if (isset($fm['title']) && is_string($fm['title'])) {
                    $title = $fm['title'];
                }
                if (isset($fm['lead']) && is_string($fm['lead'])) {
                    $lead = $fm['lead'];
                }
                if (isset($fm['quote']) && is_string($fm['quote'])) {
                    $quote = $fm['quote'];
                }
                if (isset($fm['group']) && is_string($fm['group'])) {
                    $group = $fm['group'];
                }
                if (isset($fm['order']) && is_numeric($fm['order'])) {
                    $order = (int) $fm['order'];
                }
            }
            $body = $m[2];
        }

        return new Page(
            slug: $slug,
            filename: $filename,
            body: $body,
            githubUrl: $this->buildBlobUrl($relPath),
            githubEditUrl: $this->buildEditUrl($relPath),
            title: $title,
            lead: $lead,
            quote: $quote,
            group: $group,
            order: $order,
        );
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

    private static function isValidSlug(string $slug): bool
    {
        return $slug !== '' && preg_match('/^[A-Za-z0-9][A-Za-z0-9._-]*$/', $slug) === 1;
    }
}
