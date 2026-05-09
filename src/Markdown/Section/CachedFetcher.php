<?php

namespace App\Markdown\Section;

use Symfony\Contracts\Cache\CacheInterface;
use Symfony\Contracts\Cache\ItemInterface;

/**
 * Wrapper přes inner fetcher s cache + last-known-good fallbackem.
 *
 * Failure semantics:
 *  - Inner returns null (HTTP 404) → krátká cache, vrátí null (page genuinely gone).
 *  - Inner throws (network / 5xx / rate limit) → necachuje, vrátí poslední úspěšnou
 *    odpověď z LKG (pokud existuje, jinak null / empty list).
 *
 * Cache klíče jsou prefixed `Config::name` (`docs.list`, `wiki.content.priprava`, ...).
 */
final class CachedFetcher implements FetcherInterface
{
    private const LAST_GOOD_TTL = 7 * 86400;

    public function __construct(
        private readonly FetcherInterface $inner,
        private readonly CacheInterface $cache,
        private readonly string $cachePrefix,
        private readonly int $ttlSeconds = 600,
    ) {
    }

    public function list(): array
    {
        $key = $this->cachePrefix . '.list';
        $lkgKey = $key . '.last_good';

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

    public function get(string $slug): ?Page
    {
        $key = $this->cachePrefix . '.content.' . $this->slugKey($slug);
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
