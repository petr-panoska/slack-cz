<?php

namespace App\Docs;

use Symfony\Contracts\Cache\CacheInterface;
use Symfony\Contracts\Cache\ItemInterface;

/**
 * Wraps an inner DocsFetcher with cache + last-known-good fallback.
 *
 * Failure semantics:
 *  - Inner returns null (HTTP 404) → cached briefly, returned as-is
 *    (file genuinely gone; controller renders 404).
 *  - Inner throws (network / 5xx / 403 rate limit) → not cached;
 *    we serve the most recent successful response from LKG (if any).
 */
final class CachedDocsFetcher implements DocsFetcherInterface
{
    private const LAST_GOOD_TTL = 7 * 86400;

    public function __construct(
        private readonly DocsFetcherInterface $inner,
        private readonly CacheInterface $cache,
        private readonly int $ttlSeconds = 600,
    ) {
    }

    public function list(): array
    {
        $key = 'docs.list';
        $lkgKey = 'docs.list.last_good';

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

    public function get(string $slug): ?DocFile
    {
        $key = 'docs.content.' . $this->slugKey($slug);
        $lkgKey = $key . '.last_good';

        try {
            return $this->cache->get($key, function (ItemInterface $item) use ($slug, $lkgKey) {
                $doc = $this->inner->get($slug);
                $item->expiresAfter($doc === null ? 60 : $this->ttlSeconds);
                if ($doc !== null) {
                    $this->writeLastGood($lkgKey, $doc);
                }
                return $doc;
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
        // Sanitize for cache key — match Symfony's reserved chars rules.
        return preg_replace('/[^A-Za-z0-9_.-]/', '_', $slug) ?? 'invalid';
    }
}
