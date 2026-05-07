<?php

namespace App\Feed;

use Symfony\Contracts\Cache\CacheInterface;
use Symfony\Contracts\Cache\ItemInterface;

final class CachedFeedFetcher implements FeedFetcherInterface
{
    public function __construct(
        private readonly FeedFetcherInterface $inner,
        private readonly CacheInterface $cache,
        private readonly int $ttlSeconds = 1800,
    ) {
    }

    public function fetch(int $limit = 12): array
    {
        return $this->cache->get('feed.items.' . $limit, function (ItemInterface $item) use ($limit) {
            $item->expiresAfter($this->ttlSeconds);
            return $this->inner->fetch($limit);
        });
    }
}
