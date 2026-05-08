<?php

namespace App\Feed;

use Symfony\Contracts\Cache\CacheInterface;
use Symfony\Contracts\Cache\ItemInterface;

final class CachedFeedFetcher implements FeedFetcherInterface
{
    /**
     * Hold the most recent non-empty real fetch this long. A YouTube
     * quota-exhausted day (resets at PT midnight) would otherwise blank the
     * TV panel until quota recovery; with last-known-good we keep showing
     * yesterday's items.
     */
    private const LAST_GOOD_TTL = 7 * 86400;

    public function __construct(
        private readonly FeedFetcherInterface $inner,
        private readonly CacheInterface $cache,
        private readonly MockFeedFetcher $mock,
        private readonly int $ttlSeconds = 1800,
    ) {
    }

    public function fetch(int $limit = 12): array
    {
        $freshKey = 'feed.items.' . $limit;
        $lastGoodKey = 'feed.items.last_good.' . $limit;

        $items = $this->cache->get($freshKey, function (ItemInterface $item) use ($limit, $lastGoodKey) {
            $items = $this->inner->fetch($limit);

            // Don't cache an empty result for the full TTL — a transient YouTube
            // failure (quota exhausted, network blip) would otherwise hide the
            // whole TV panel for hours. Cache emptiness briefly so we retry
            // soon, but not on every single request.
            $item->expiresAfter($items === [] ? 60 : $this->ttlSeconds);

            // Refresh the last-known-good copy on every successful real fetch.
            if ($items !== []) {
                $this->cache->delete($lastGoodKey);
                $this->cache->get($lastGoodKey, function (ItemInterface $i) use ($items) {
                    $i->expiresAfter(self::LAST_GOOD_TTL);
                    return $items;
                });
            }

            return $items;
        });

        if ($items !== []) {
            return $items;
        }

        // Fresh fetch came back empty (quota / network / no results). Try the
        // last-known-good first. The closure runs only on a true miss — null
        // is a sentinel so we don't manufacture a stale empty.
        $lastGood = $this->cache->get($lastGoodKey, function (ItemInterface $i) {
            $i->expiresAfter(60);
            return null;
        });

        if ($lastGood !== null && $lastGood !== []) {
            return $lastGood;
        }

        // Nothing real, ever. Render mock placeholders so the panel exists at
        // all — but DO NOT cache them. Next request will retry youtube and
        // promote real data into the LKG slot the moment quota recovers.
        return $this->mock->fetch($limit);
    }
}
