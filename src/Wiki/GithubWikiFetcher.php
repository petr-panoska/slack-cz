<?php

namespace App\Wiki;

use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\Yaml\Yaml;
use Symfony\Contracts\HttpClient\Exception\ExceptionInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;

/**
 * Reads wiki .md files from a GitHub repository.
 *
 * Stejný pattern jako App\Docs\GithubDocsFetcher, ale parsuje frontmatter
 * (title / lead / quote / group / order) z hlavičky každého MD souboru.
 *
 * `list()` provádí list+fetch obou v jediném průchodu (potřebujeme metadata
 * z frontmatter pro index pohled).
 */
final class GithubWikiFetcher implements WikiFetcherInterface
{
    public function __construct(
        private readonly HttpClientInterface $httpClient,
        #[Autowire('%wiki.github.owner%')]
        private readonly string $owner,
        #[Autowire('%wiki.github.repo%')]
        private readonly string $repo,
        #[Autowire('%wiki.github.branch%')]
        private readonly string $branch,
        #[Autowire('%wiki.github.path%')]
        private readonly string $path,
        #[Autowire('%env(default::WIKI_GITHUB_TOKEN)%')]
        private readonly ?string $token = null,
    ) {
    }

    public function list(): array
    {
        $rows = $this->fetchContents();
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
            $page = $this->get($slug);
            if ($page === null) {
                continue;
            }
            $entries[] = new WikiEntry(
                slug: $page->slug,
                filename: $page->filename,
                title: $page->title,
                lead: $page->lead,
                group: $page->group,
                order: $page->order,
                githubUrl: $page->githubUrl,
            );
        }

        usort($entries, function (WikiEntry $a, WikiEntry $b) {
            return $a->order <=> $b->order;
        });

        return $entries;
    }

    public function get(string $slug): ?WikiPage
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

        $raw = $response->getContent(false);

        return $this->parsePage($slug, $filename, $raw);
    }

    /**
     * @return list<array<string,mixed>>
     */
    private function fetchContents(): array
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

        return $response->toArray(false);
    }

    private function parsePage(string $slug, string $filename, string $raw): WikiPage
    {
        $title = self::humanize($slug);
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

        return new WikiPage(
            slug: $slug,
            filename: $filename,
            title: $title,
            lead: $lead,
            quote: $quote,
            group: $group,
            order: $order,
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
            'User-Agent' => 'slack-cz-wiki/1.0',
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
        $s = str_replace(['-', '_'], ' ', $slug);
        return ucfirst($s);
    }
}
