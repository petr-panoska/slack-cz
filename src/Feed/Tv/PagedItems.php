<?php

namespace App\Feed\Tv;

use App\Feed\FeedItem;

/**
 * One page of a YouTube listing: the items plus the opaque token for the next
 * page (null when the listing has no more results).
 */
final readonly class PagedItems
{
    /**
     * @param list<FeedItem> $items
     */
    public function __construct(
        public array $items,
        public ?string $nextPageToken = null,
    ) {
    }
}
