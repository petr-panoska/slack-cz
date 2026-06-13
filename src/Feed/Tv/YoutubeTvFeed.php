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

    /** @var list<TvSource> */
    private readonly array $channels;

    /** @var list<TvSource> */
    private readonly array $playlists;

    /**
     * @param list<string|array{id?: string, sort?: string}> $channels  @handle / UC… id, or {id, sort}
     * @param list<string|array{id?: string, sort?: string}> $playlists PL… id, or {id, sort}
     * @param list<string>                                    $hashtags  e.g. "#czechslackline"
     */
    public function __construct(
        private readonly YoutubeClient $client,
        #[Autowire('%feed.tv.channels%')]
        array $channels,
        #[Autowire('%feed.tv.playlists%')]
        array $playlists,
        #[Autowire('%feed.tv.hashtags%')]
        private readonly array $hashtags,
    ) {
        $this->channels = array_map(TvSource::fromConfig(...), $channels);
        $this->playlists = array_map(TvSource::fromConfig(...), $playlists);
    }

    public function sections(): array
    {
        if (!$this->client->isConfigured()) {
            return ['channels' => [], 'playlists' => [], 'hashtags' => []];
        }

        return [
            'channels' => $this->buildGroups($this->channels, fn (TvSource $s) => $this->channelGroup($s, null)),
            'playlists' => $this->buildGroups($this->playlists, fn (TvSource $s) => $this->playlistGroup($s, null)),
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
            'channel' => $this->channelGroup($this->source($this->channels, $ref), $pageToken),
            'playlist' => $this->playlistGroup($this->source($this->playlists, $ref), $pageToken),
            'hashtag' => $this->hashtagGroup($ref, $pageToken),
            default => null,
        };
    }

    public function knownKeys(): array
    {
        return [
            ...array_map(static fn (TvSource $s) => 'channel:' . $s->id, $this->channels),
            ...array_map(static fn (TvSource $s) => 'playlist:' . $s->id, $this->playlists),
            ...array_map(static fn (string $tag) => 'hashtag:' . $tag, $this->hashtags),
        ];
    }

    /**
     * @template T
     * @param list<T>             $items
     * @param callable(T): ?FeedGroup $build
     * @return list<FeedGroup>
     */
    private function buildGroups(array $items, callable $build): array
    {
        $groups = [];
        foreach ($items as $item) {
            $group = $build($item);
            if ($group !== null) {
                $groups[] = $group;
            }
        }

        return $groups;
    }

    /**
     * The TvSource behind a channel/playlist key. page() only reaches here after
     * the key passed the knownKeys check, so a match is guaranteed; the default
     * (newest-first) fallback is unreachable and just satisfies the return type.
     *
     * @param list<TvSource> $sources
     */
    private function source(array $sources, string $id): TvSource
    {
        foreach ($sources as $source) {
            if ($source->id === $id) {
                return $source;
            }
        }

        return new TvSource($id);
    }

    private function channelGroup(TvSource $source, ?string $pageToken): ?FeedGroup
    {
        $info = $this->client->resolveChannel($source->id);
        if ($info === null) {
            return null;
        }

        $key = 'channel:' . $source->id;
        if ($source->oldestFirst) {
            return $this->oldestFirstGroup($info['uploads'], $key, 'channel', $info['title'], $info['url'], $pageToken);
        }

        $page = $this->client->playlistItems($info['uploads'], $pageToken, self::PAGE_SIZE);
        if ($page->items === [] && $pageToken === null) {
            return null;
        }

        return new FeedGroup(
            key: $key,
            kind: 'channel',
            title: $info['title'],
            url: $info['url'],
            items: $page->items,
            nextPageToken: $page->nextPageToken,
        );
    }

    private function playlistGroup(TvSource $source, ?string $pageToken): ?FeedGroup
    {
        $info = $this->client->resolvePlaylist($source->id);
        if ($info === null) {
            return null;
        }

        $key = 'playlist:' . $source->id;
        if ($source->oldestFirst) {
            return $this->oldestFirstGroup($source->id, $key, 'playlist', $info['title'], $info['url'], $pageToken);
        }

        $page = $this->client->playlistItems($source->id, $pageToken, self::PAGE_SIZE);
        if ($page->items === [] && $pageToken === null) {
            return null;
        }

        return new FeedGroup(
            key: $key,
            kind: 'playlist',
            title: $info['title'],
            url: $info['url'],
            items: $page->items,
            nextPageToken: $page->nextPageToken,
        );
    }

    /**
     * Oldest-first slider for a channel's uploads or a playlist. The YouTube API
     * can't order playlistItems in reverse, so we pull the whole listing, flip
     * it, and paginate locally: the page token is a plain integer offset (the
     * load-more endpoint round-trips it as a string, which is fine). Offsets are
     * counted from the oldest item, so they stay stable as new videos are added.
     *
     * @param 'channel'|'playlist' $kind
     */
    private function oldestFirstGroup(string $playlistId, string $key, string $kind, string $title, string $url, ?string $pageToken): ?FeedGroup
    {
        $all = array_reverse($this->client->allPlaylistItems($playlistId));
        if ($all === [] && $pageToken === null) {
            return null;
        }

        $offset = max(0, (int) $pageToken);
        $items = array_values(array_slice($all, $offset, self::PAGE_SIZE));
        $next = $offset + self::PAGE_SIZE < count($all)
            ? (string) ($offset + self::PAGE_SIZE)
            : null;

        return new FeedGroup(
            key: $key,
            kind: $kind,
            title: $title,
            url: $url,
            items: $items,
            nextPageToken: $next,
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
