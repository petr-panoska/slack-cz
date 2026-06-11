<?php

namespace App\Feed\Tv;

use App\Feed\FeedGroup;
use App\Feed\MockFeedFetcher;
use Symfony\Contracts\Cache\CacheInterface;
use Symfony\Contracts\Cache\ItemInterface;

/**
 * Caches the slackTV sections (one expensive bundle of API calls — see
 * YoutubeTvFeed) and individual load-more pages. Mirrors CachedFeedFetcher's
 * resilience: a short TTL on an empty result so a transient YouTube failure
 * doesn't blank the page for hours, a 7-day last-known-good copy to survive a
 * quota-exhausted day, and a mock SLACKHOVOR group as the final fallback so the
 * page is never empty.
 */
final class CachedTvFeed implements TvFeedInterface
{
    private const SECTIONS_KEY = 'tv.sections';
    private const LAST_GOOD_KEY = 'tv.sections.last_good';
    private const LAST_GOOD_TTL = 7 * 86400;

    public function __construct(
        private readonly TvFeedInterface $inner,
        private readonly CacheInterface $cache,
        private readonly MockFeedFetcher $mock,
        private readonly int $ttlSeconds = 1800,
    ) {
    }

    public function sections(): array
    {
        $sections = $this->cache->get(self::SECTIONS_KEY, function (ItemInterface $item) {
            $sections = $this->inner->sections();
            $empty = $this->isEmpty($sections);

            // Don't cache an empty result for the full TTL — a transient YouTube
            // failure would otherwise hide the whole page for hours.
            $item->expiresAfter($empty ? 60 : $this->ttlSeconds);

            if (!$empty) {
                $this->cache->delete(self::LAST_GOOD_KEY);
                $this->cache->get(self::LAST_GOOD_KEY, function (ItemInterface $i) use ($sections) {
                    $i->expiresAfter(self::LAST_GOOD_TTL);

                    return $sections;
                });
            }

            return $sections;
        });

        if (!$this->isEmpty($sections)) {
            return $sections;
        }

        // Fresh fetch came back empty — try last-known-good (null sentinel so the
        // closure only manufactures one on a true miss).
        $lastGood = $this->cache->get(self::LAST_GOOD_KEY, function (ItemInterface $i) {
            $i->expiresAfter(60);

            return null;
        });

        if ($lastGood !== null && !$this->isEmpty($lastGood)) {
            return $lastGood;
        }

        // Nothing real, ever — render the mock placeholder group, uncached.
        return $this->mockSections();
    }

    public function page(string $key, ?string $pageToken): ?FeedGroup
    {
        // Reject unknown keys before touching the cache, so a flood of arbitrary
        // keys can't pollute cache.app (one entry per key) on top of the API cost
        // the inner feed already guards against.
        if (!in_array($key, $this->inner->knownKeys(), true)) {
            return null;
        }

        $cacheKey = 'tv.page.' . md5($key . '|' . ($pageToken ?? ''));

        return $this->cache->get($cacheKey, function (ItemInterface $item) use ($key, $pageToken) {
            $group = $this->inner->page($key, $pageToken);
            $item->expiresAfter($group === null ? 60 : $this->ttlSeconds);

            return $group;
        });
    }

    public function knownKeys(): array
    {
        return $this->inner->knownKeys();
    }

    /**
     * @param array{channels: list<FeedGroup>, playlists: list<FeedGroup>, hashtags: list<FeedGroup>} $sections
     */
    private function isEmpty(array $sections): bool
    {
        return ($sections['channels'] ?? []) === []
            && ($sections['playlists'] ?? []) === []
            && ($sections['hashtags'] ?? []) === [];
    }

    /**
     * @return array{channels: list<FeedGroup>, playlists: list<FeedGroup>, hashtags: list<FeedGroup>}
     */
    private function mockSections(): array
    {
        $items = $this->mock->fetch(12);
        if ($items === []) {
            return ['channels' => [], 'playlists' => [], 'hashtags' => []];
        }

        $group = new FeedGroup(
            key: 'playlist:mock',
            kind: 'playlist',
            title: 'SLACKHOVOR',
            url: 'https://www.youtube.com/playlist?list=PLoxabYJhgiYDSZr1c694xILPE_endi7FP',
            items: $items,
            nextPageToken: null,
        );

        return ['channels' => [], 'playlists' => [$group], 'hashtags' => []];
    }
}
