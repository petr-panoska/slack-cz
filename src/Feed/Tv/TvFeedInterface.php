<?php

namespace App\Feed\Tv;

use App\Feed\FeedGroup;

interface TvFeedInterface
{
    /**
     * First page of every configured section. Each FeedGroup carries its first
     * batch of items plus a nextPageToken for load-more.
     *
     * @return array{channels: list<FeedGroup>, foreignChannels: list<FeedGroup>, playlists: list<FeedGroup>, hashtags: list<FeedGroup>}
     */
    public function sections(): array;

    /**
     * One further page for a single source, addressed by its FeedGroup::key
     * (e.g. "channel:@PhilipBitnar"). Null when the key is unknown or the source
     * is exhausted/unavailable.
     */
    public function page(string $key, ?string $pageToken): ?FeedGroup;

    /**
     * The valid FeedGroup::key values (the configured sources). The load-more
     * endpoint checks membership before doing anything, so an arbitrary key
     * can't trigger a YouTube call or a cache write.
     *
     * @return list<string>
     */
    public function knownKeys(): array;
}
