<?php

namespace App\Feed\Tv;

use App\Feed\FeedItem;
use Psr\Log\LoggerInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Contracts\HttpClient\Exception\ExceptionInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;

/**
 * Thin wrapper over the YouTube Data API v3 for the slackTV section feed. Knows
 * how to page through a channel's uploads, a playlist or a hashtag search, and
 * to resolve a channel @handle to its uploads playlist + title.
 *
 * Quota: channels/playlists/playlistItems list calls cost 1 unit each; search
 * costs 100 units (so hashtags are the only expensive source). maxResults is
 * capped at 50 by the API regardless, and search's cost is flat per call.
 */
final class YoutubeClient
{
    private const BASE = 'https://www.googleapis.com/youtube/v3';

    public function __construct(
        private readonly HttpClientInterface $http,
        private readonly LoggerInterface $logger,
        #[Autowire('%env(default::YOUTUBE_API_KEY)%')]
        private readonly ?string $apiKey,
    ) {
    }

    public function isConfigured(): bool
    {
        return $this->apiKey !== null && $this->apiKey !== '';
    }

    /**
     * Resolve a channel reference (an @handle or a UC… id) to its title, URL and
     * uploads-playlist id. One quota unit. Null if not found / on error.
     *
     * @return array{title: string, url: string, uploads: string}|null
     */
    public function resolveChannel(string $ref): ?array
    {
        $params = ['part' => 'contentDetails,snippet'];
        if (str_starts_with($ref, '@')) {
            $params['forHandle'] = ltrim($ref, '@');
        } else {
            $params['id'] = $ref;
        }

        $info = $this->call('/channels', $params)['items'][0] ?? null;
        $uploads = $info['contentDetails']['relatedPlaylists']['uploads'] ?? null;
        if ($info === null || $uploads === null) {
            return null;
        }

        $id = $info['id'] ?? null;

        return [
            'title' => $info['snippet']['title'] ?? $ref,
            'url' => $id !== null
                ? 'https://www.youtube.com/channel/' . $id
                : 'https://www.youtube.com/' . $ref,
            'uploads' => $uploads,
        ];
    }

    /**
     * @return array{title: string, url: string}|null
     */
    public function resolvePlaylist(string $playlistId): ?array
    {
        $info = $this->call('/playlists', [
            'part' => 'snippet',
            'id' => $playlistId,
        ])['items'][0] ?? null;

        if ($info === null) {
            return null;
        }

        return [
            'title' => $info['snippet']['title'] ?? $playlistId,
            'url' => 'https://www.youtube.com/playlist?list=' . $playlistId,
        ];
    }

    public function playlistItems(string $playlistId, ?string $pageToken, int $max): PagedItems
    {
        $params = [
            'part' => 'snippet,contentDetails',
            'playlistId' => $playlistId,
            'maxResults' => min(50, $max),
        ];
        if ($pageToken !== null && $pageToken !== '') {
            $params['pageToken'] = $pageToken;
        }

        $data = $this->call('/playlistItems', $params);

        $items = [];
        foreach ($data['items'] ?? [] as $row) {
            $videoId = $row['contentDetails']['videoId'] ?? null;
            $snippet = $row['snippet'] ?? null;
            // Deleted/private videos linger in playlists with an empty thumbnail
            // set — skip those rather than render a broken card.
            if ($videoId === null || $snippet === null || ($snippet['thumbnails'] ?? []) === []) {
                continue;
            }
            $items[] = $this->buildItem(
                $videoId,
                $snippet,
                $snippet['videoOwnerChannelTitle'] ?? ($snippet['channelTitle'] ?? ''),
                $snippet['videoOwnerChannelId'] ?? ($snippet['channelId'] ?? ''),
            );
        }

        return new PagedItems($items, $data['nextPageToken'] ?? null);
    }

    public function search(string $query, ?string $pageToken, int $max): PagedItems
    {
        $params = [
            'part' => 'snippet',
            'q' => $query,
            'type' => 'video',
            'order' => 'date',
            'maxResults' => min(50, $max),
        ];
        if ($pageToken !== null && $pageToken !== '') {
            $params['pageToken'] = $pageToken;
        }

        $data = $this->call('/search', $params);

        $items = [];
        foreach ($data['items'] ?? [] as $row) {
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

        return new PagedItems($items, $data['nextPageToken'] ?? null);
    }

    /**
     * @param array<string, scalar> $params
     * @return array<string, mixed>
     */
    private function call(string $path, array $params): array
    {
        $params['key'] = $this->apiKey;

        try {
            return $this->http->request('GET', self::BASE . $path, [
                'query' => $params,
                'timeout' => 6,
            ])->toArray(false);
        } catch (ExceptionInterface $e) {
            $this->logger->warning('YouTube API call failed', ['path' => $path, 'error' => $e->getMessage()]);

            return [];
        }
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
            ?? 'https://i.ytimg.com/vi/' . $videoId . '/hqdefault.jpg';

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
}
