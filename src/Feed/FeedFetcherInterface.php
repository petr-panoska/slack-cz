<?php

namespace App\Feed;

interface FeedFetcherInterface
{
    /**
     * @return list<FeedItem> latest items, sorted by publishedAt DESC
     */
    public function fetch(int $limit = 12): array;
}
