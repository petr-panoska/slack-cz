<?php

namespace App\Wiki;

use Symfony\Contracts\Cache\CacheInterface;
use Symfony\Contracts\Cache\ItemInterface;

/**
 * Wraps an inner WikiFetcher with cache + last-known-good fallback.
 *
 * Failure semantics:
 *  - Inner returns null (HTTP 404) → cached briefly, returned as-is
 *    (page genuinely gone; controller renders 404).
 *  - Inner throws (network / 5xx / 403 rate limit) → not cached;
 *    we serve the most recent successful response from LKG (if any).
 *
 * Stejný pattern jako App\Docs\CachedDocsFetcher.
 */
final class CachedWikiFetcher implements WikiFetcherInterface
{
    private const LAST_GOOD_TTL = 7 * 86400;

    public function __construct(
        private readonly WikiFetcherInterface $inner,
        private readonly CacheInterface $cache,
        private readonly int $ttlSeconds = 600,
    ) {
    }

    public function list(): array
    {
        $key = 'wiki.list';
        $lkgKey = 'wiki.list.last_good';

        try {
            $entries = $this->cache->get($key, function (ItemInterface $item) use ($lkgKey) {
                $entries = $this->inner->list();
                $item->expiresAfter($entries === [] ? 60 : $this->ttlSeconds);
                if ($entries !== []) {
                    $this->writeLastGood($lkgKey, $entries);
                }
                return $entries;
            });
            if ($entries !== []) {
                return $entries;
            }
        } catch (\Throwable) {
            // fall through to LKG
        }

        return $this->readLastGood($lkgKey) ?? [];
    }

    public function get(string $slug): ?WikiPage
    {
        $key = 'wiki.content.' . $this->slugKey($slug);
        $lkgKey = $key . '.last_good';

        try {
            return $this->cache->get($key, function (ItemInterface $item) use ($slug, $lkgKey) {
                $page = $this->inner->get($slug);
                $item->expiresAfter($page === null ? 60 : $this->ttlSeconds);
                if ($page !== null) {
                    $this->writeLastGood($lkgKey, $page);
                }
                return $page;
            });
        } catch (\Throwable) {
            return $this->readLastGood($lkgKey);
        }
    }

    private function writeLastGood(string $key, mixed $value): void
    {
        $this->cache->delete($key);
        $this->cache->get($key, function (ItemInterface $item) use ($value) {
            $item->expiresAfter(self::LAST_GOOD_TTL);
            return $value;
        });
    }

    private function readLastGood(string $key): mixed
    {
        return $this->cache->get($key, function (ItemInterface $item) {
            $item->expiresAfter(60);
            return null;
        });
    }

    private function slugKey(string $slug): string
    {
        return preg_replace('/[^A-Za-z0-9_.-]/', '_', $slug) ?? 'invalid';
    }
}
