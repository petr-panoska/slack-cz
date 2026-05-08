<?php

namespace App\Docs;

use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Contracts\HttpClient\Exception\ExceptionInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;

/**
 * Reads .md files from a GitHub repository as the source of truth for /docs.
 *
 * Two endpoints:
 *  - List:    https://api.github.com/repos/{owner}/{repo}/contents/{path}?ref={branch}
 *             (counts against API rate limit: 60/h/IP unauth, 5000/h authed)
 *  - Content: https://raw.githubusercontent.com/{owner}/{repo}/{branch}/{path}/{file}.md
 *             (CDN, no API rate limit)
 */
final class GithubDocsFetcher implements DocsFetcherInterface
{
    public function __construct(
        private readonly HttpClientInterface $httpClient,
        #[Autowire('%docs.github.owner%')]
        private readonly string $owner,
        #[Autowire('%docs.github.repo%')]
        private readonly string $repo,
        #[Autowire('%docs.github.branch%')]
        private readonly string $branch,
        #[Autowire('%docs.github.path%')]
        private readonly string $path,
        #[Autowire('%env(default::DOCS_GITHUB_TOKEN)%')]
        private readonly ?string $token = null,
    ) {
    }

    public function list(): array
    {
        $url = sprintf(
            'https://api.github.com/repos/%s/%s/contents/%s?ref=%s',
            rawurlencode($this->owner),
            rawurlencode($this->repo),
            $this->path,
            rawurlencode($this->branch),
        );

        $response = $this->httpClient->request('GET', $url, [
            'headers' => $this->headers(['Accept' => 'application/vnd.github+json']),
        ]);

        $status = $response->getStatusCode();
        if ($status !== 200) {
            throw new \RuntimeException(sprintf('GitHub contents API returned HTTP %d for %s', $status, $url));
        }

        $rows = $response->toArray(false);
        $entries = [];
        foreach ($rows as $row) {
            if (($row['type'] ?? null) !== 'file') {
                continue;
            }
            $name = $row['name'] ?? '';
            if (!str_ends_with($name, '.md')) {
                continue;
            }
            $slug = substr($name, 0, -3);
            if (!self::isValidSlug($slug)) {
                continue;
            }
            $entries[] = new DocsListEntry(
                slug: $slug,
                filename: $name,
                label: self::humanize($slug),
                githubUrl: $row['html_url'] ?? $this->buildBlobUrl($name),
            );
        }

        usort($entries, function (DocsListEntry $a, DocsListEntry $b) {
            // README first, then alphabetical.
            if (strcasecmp($a->slug, 'README') === 0) {
                return -1;
            }
            if (strcasecmp($b->slug, 'README') === 0) {
                return 1;
            }
            return strcmp($a->label, $b->label);
        });

        return $entries;
    }

    public function get(string $slug): ?DocFile
    {
        if (!self::isValidSlug($slug)) {
            return null;
        }
        $filename = $slug . '.md';
        $url = sprintf(
            'https://raw.githubusercontent.com/%s/%s/%s/%s/%s',
            rawurlencode($this->owner),
            rawurlencode($this->repo),
            rawurlencode($this->branch),
            $this->path,
            rawurlencode($filename),
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

        return new DocFile(
            slug: $slug,
            filename: $filename,
            label: self::humanize($slug),
            body: $body,
            githubUrl: $this->buildBlobUrl($filename),
            githubEditUrl: $this->buildEditUrl($filename),
        );
    }

    private function buildBlobUrl(string $filename): string
    {
        return sprintf(
            'https://github.com/%s/%s/blob/%s/%s/%s',
            $this->owner,
            $this->repo,
            $this->branch,
            $this->path,
            $filename,
        );
    }

    private function buildEditUrl(string $filename): string
    {
        return sprintf(
            'https://github.com/%s/%s/edit/%s/%s/%s',
            $this->owner,
            $this->repo,
            $this->branch,
            $this->path,
            $filename,
        );
    }

    /**
     * @param array<string,string> $extra
     * @return array<string,string>
     */
    private function headers(array $extra = []): array
    {
        $base = [
            'User-Agent' => 'slack-cz-docs/1.0',
        ];
        if ($this->token !== null && $this->token !== '') {
            $base['Authorization'] = 'Bearer ' . $this->token;
        }
        return $base + $extra;
    }

    private static function isValidSlug(string $slug): bool
    {
        return $slug !== '' && preg_match('/^[A-Za-z0-9][A-Za-z0-9._-]*$/', $slug) === 1;
    }

    private static function humanize(string $slug): string
    {
        if (strcasecmp($slug, 'README') === 0) {
            return 'README';
        }
        $s = str_replace(['-', '_'], ' ', $slug);
        return ucfirst($s);
    }
}
