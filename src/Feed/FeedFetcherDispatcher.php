<?php

namespace App\Feed;

/**
 * Picks the YouTube fetcher when an API key is configured; otherwise falls back to mock data.
 */
final class FeedFetcherDispatcher implements FeedFetcherInterface
{
    public function __construct(
        private readonly YoutubeFeedFetcher $youtube,
        private readonly MockFeedFetcher $mock,
    ) {
    }

    public function fetch(int $limit = 12): array
    {
        return $this->youtube->isConfigured()
            ? $this->youtube->fetch($limit)
            : $this->mock->fetch($limit);
    }
}
