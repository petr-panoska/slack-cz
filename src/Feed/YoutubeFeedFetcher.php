<?php

namespace App\Feed;

use Psr\Log\LoggerInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Contracts\HttpClient\Exception\ExceptionInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;

final class YoutubeFeedFetcher implements FeedFetcherInterface
{
    private const BASE = 'https://www.googleapis.com/youtube/v3';

    /**
     * @param list<string> $channels  YouTube channel IDs (UC...)
     * @param list<string> $queries   search queries (can include hashtags like "#czechslackline")
     */
    public function __construct(
        private readonly HttpClientInterface $http,
        private readonly LoggerInterface $logger,
        #[Autowire('%env(default::YOUTUBE_API_KEY)%')]
        private readonly ?string $apiKey,
        #[Autowire('%feed.youtube.channels%')]
        private readonly array $channels,
        #[Autowire('%feed.youtube.queries%')]
        private readonly array $queries,
    ) {
    }

    public function isConfigured(): bool
    {
        return $this->apiKey !== null && $this->apiKey !== '';
    }

    public function fetch(int $limit = 12): array
    {
        if (!$this->isConfigured()) {
            return [];
        }

        $items = [];

        foreach ($this->channels as $channelId) {
            try {
                $items = array_merge($items, $this->fetchChannelUploads($channelId));
            } catch (ExceptionInterface $e) {
                $this->logger->warning('YouTube channel fetch failed', ['channel' => $channelId, 'error' => $e->getMessage()]);
            }
        }

        foreach ($this->queries as $query) {
            try {
                $items = array_merge($items, $this->fetchSearch($query));
            } catch (ExceptionInterface $e) {
                $this->logger->warning('YouTube search fetch failed', ['query' => $query, 'error' => $e->getMessage()]);
            }
        }

        $items = $this->dedupe($items);
        usort($items, fn (FeedItem $a, FeedItem $b) => $b->publishedAt <=> $a->publishedAt);

        return array_slice($items, 0, $limit);
    }

    /**
     * @return list<FeedItem>
     */
    private function fetchChannelUploads(string $channelId): array
    {
        $channel = $this->call('/channels', [
            'part' => 'contentDetails,snippet',
            'id' => $channelId,
        ]);

        $info = $channel['items'][0] ?? null;
        if ($info === null) {
            return [];
        }

        $uploadsPlaylist = $info['contentDetails']['relatedPlaylists']['uploads'] ?? null;
        $authorName = $info['snippet']['title'] ?? $channelId;
        if ($uploadsPlaylist === null) {
            return [];
        }

        $playlist = $this->call('/playlistItems', [
            'part' => 'snippet,contentDetails',
            'playlistId' => $uploadsPlaylist,
            'maxResults' => 10,
        ]);

        $items = [];
        foreach ($playlist['items'] ?? [] as $row) {
            $videoId = $row['contentDetails']['videoId'] ?? null;
            $snippet = $row['snippet'] ?? null;
            if ($videoId === null || $snippet === null) {
                continue;
            }
            $items[] = $this->buildItem($videoId, $snippet, $authorName, $channelId);
        }
        return $items;
    }

    /**
     * @return list<FeedItem>
     */
    private function fetchSearch(string $query): array
    {
        $result = $this->call('/search', [
            'part' => 'snippet',
            'q' => $query,
            'type' => 'video',
            'order' => 'date',
            'maxResults' => 10,
        ]);

        $items = [];
        foreach ($result['items'] ?? [] as $row) {
            $videoId = $row['id']['videoId'] ?? null;
            $snippet = $row['snippet'] ?? null;
            if ($videoId === null || $snippet === null) {
                continue;
            }
            $items[] = $this->buildItem(
                $videoId,
                $snippet,
                $snippet['channelTitle'] ?? '',
                $snippet['channelId'] ?? '',
            );
        }
        return $items;
    }

    /**
     * @param array<string, scalar> $params
     * @return array<string, mixed>
     */
    private function call(string $path, array $params): array
    {
        $params['key'] = $this->apiKey;
        $response = $this->http->request('GET', self::BASE . $path, [
            'query' => $params,
            'timeout' => 6,
        ]);
        return $response->toArray(false);
    }

    /**
     * @param array<string, mixed> $snippet
     */
    private function buildItem(string $videoId, array $snippet, string $authorName, string $channelId): FeedItem
    {
        $thumbs = $snippet['thumbnails'] ?? [];
        $thumbUrl = $thumbs['high']['url']
            ?? $thumbs['medium']['url']
            ?? $thumbs['default']['url']
            ?? '';

        return new FeedItem(
            id: 'yt:' . $videoId,
            title: $snippet['title'] ?? '',
            description: $snippet['description'] ?? null,
            publishedAt: new \DateTimeImmutable($snippet['publishedAt'] ?? 'now'),
            thumbnailUrl: $thumbUrl,
            link: 'https://www.youtube.com/watch?v=' . $videoId,
            source: 'youtube',
            authorName: $authorName,
            authorUrl: $channelId !== '' ? 'https://www.youtube.com/channel/' . $channelId : null,
        );
    }

    /**
     * @param list<FeedItem> $items
     * @return list<FeedItem>
     */
    private function dedupe(array $items): array
    {
        $seen = [];
        $out = [];
        foreach ($items as $item) {
            if (isset($seen[$item->id])) {
                continue;
            }
            $seen[$item->id] = true;
            $out[] = $item;
        }
        return $out;
    }
}
