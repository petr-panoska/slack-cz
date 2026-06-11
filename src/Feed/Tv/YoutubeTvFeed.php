<?php

namespace App\Feed\Tv;

use App\Feed\FeedGroup;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

/**
 * Builds the slackTV sections from the YouTube Data API. Channels are given as
 * @handles (resolved to their uploads playlist), playlists by id, hashtags as
 * search queries. Page size is small — the slider shows a first batch and the
 * load-more endpoint pulls further pages via FeedGroup::key. A single source
 * that fails is skipped, not fatal to the whole page.
 */
final class YoutubeTvFeed implements TvFeedInterface
{
    private const PAGE_SIZE = 12;

    /**
     * @param list<string> $channels  @handles or UC… ids
     * @param list<string> $playlists PL… ids
     * @param list<string> $hashtags  e.g. "#czechslackline"
     */
    public function __construct(
        private readonly YoutubeClient $client,
        #[Autowire('%feed.tv.channels%')]
        private readonly array $channels,
        #[Autowire('%feed.tv.playlists%')]
        private readonly array $playlists,
        #[Autowire('%feed.tv.hashtags%')]
        private readonly array $hashtags,
    ) {
    }

    public function sections(): array
    {
        if (!$this->client->isConfigured()) {
            return ['channels' => [], 'playlists' => [], 'hashtags' => []];
        }

        return [
            'channels' => $this->buildGroups($this->channels, fn (string $ref) => $this->channelGroup($ref, null)),
            'playlists' => $this->buildGroups($this->playlists, fn (string $id) => $this->playlistGroup($id, null)),
            'hashtags' => $this->buildGroups($this->hashtags, fn (string $tag) => $this->hashtagGroup($tag, null)),
        ];
    }

    public function page(string $key, ?string $pageToken): ?FeedGroup
    {
        // Only configured sources may be paged — an arbitrary key must not reach
        // the API (search = 100 quota units / call).
        if (!in_array($key, $this->knownKeys(), true)) {
            return null;
        }

        [$kind, $ref] = array_pad(explode(':', $key, 2), 2, '');

        return match ($kind) {
            'channel' => $this->channelGroup($ref, $pageToken),
            'playlist' => $this->playlistGroup($ref, $pageToken),
            'hashtag' => $this->hashtagGroup($ref, $pageToken),
            default => null,
        };
    }

    public function knownKeys(): array
    {
        return [
            ...array_map(static fn (string $ref) => 'channel:' . $ref, $this->channels),
            ...array_map(static fn (string $id) => 'playlist:' . $id, $this->playlists),
            ...array_map(static fn (string $tag) => 'hashtag:' . $tag, $this->hashtags),
        ];
    }

    /**
     * @param list<string>                    $refs
     * @param callable(string): ?FeedGroup    $build
     * @return list<FeedGroup>
     */
    private function buildGroups(array $refs, callable $build): array
    {
        $groups = [];
        foreach ($refs as $ref) {
            $group = $build($ref);
            if ($group !== null) {
                $groups[] = $group;
            }
        }

        return $groups;
    }

    private function channelGroup(string $ref, ?string $pageToken): ?FeedGroup
    {
        $info = $this->client->resolveChannel($ref);
        if ($info === null) {
            return null;
        }

        $page = $this->client->playlistItems($info['uploads'], $pageToken, self::PAGE_SIZE);
        if ($page->items === [] && $pageToken === null) {
            return null;
        }

        return new FeedGroup(
            key: 'channel:' . $ref,
            kind: 'channel',
            title: $info['title'],
            url: $info['url'],
            items: $page->items,
            nextPageToken: $page->nextPageToken,
        );
    }

    private function playlistGroup(string $id, ?string $pageToken): ?FeedGroup
    {
        $info = $this->client->resolvePlaylist($id);
        if ($info === null) {
            return null;
        }

        $page = $this->client->playlistItems($id, $pageToken, self::PAGE_SIZE);
        if ($page->items === [] && $pageToken === null) {
            return null;
        }

        return new FeedGroup(
            key: 'playlist:' . $id,
            kind: 'playlist',
            title: $info['title'],
            url: $info['url'],
            items: $page->items,
            nextPageToken: $page->nextPageToken,
        );
    }

    private function hashtagGroup(string $tag, ?string $pageToken): ?FeedGroup
    {
        $page = $this->client->search($tag, $pageToken, self::PAGE_SIZE);
        if ($page->items === [] && $pageToken === null) {
            return null;
        }

        return new FeedGroup(
            key: 'hashtag:' . $tag,
            kind: 'hashtag',
            title: $tag,
            url: 'https://www.youtube.com/hashtag/' . ltrim($tag, '#'),
            items: $page->items,
            nextPageToken: $page->nextPageToken,
        );
    }
}
