<?php

namespace App\Feed;

final readonly class FeedItem
{
    public function __construct(
        public string $id,
        public string $title,
        public ?string $description,
        public \DateTimeImmutable $publishedAt,
        public string $thumbnailUrl,
        public string $link,
        public string $source,
        public string $authorName,
        public ?string $authorUrl = null,
    ) {
    }
}
