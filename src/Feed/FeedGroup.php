<?php

namespace App\Feed;

/**
 * A named row of videos on the slackTV page — one YouTube channel, playlist or
 * hashtag. `key` is the stable source identifier the load-more endpoint uses to
 * fetch the next page (e.g. "channel:@PhilipBitnar", "playlist:PL…",
 * "hashtag:#czechslackline"); `nextPageToken` is null once the source has no
 * more pages.
 */
final readonly class FeedGroup
{
    /**
     * @param 'channel'|'playlist'|'hashtag' $kind
     * @param list<FeedItem>                 $items
     */
    public function __construct(
        public string $key,
        public string $kind,
        public string $title,
        public ?string $url,
        public array $items,
        public ?string $nextPageToken = null,
    ) {
    }
}
