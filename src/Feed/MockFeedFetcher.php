<?php

namespace App\Feed;

final class MockFeedFetcher implements FeedFetcherInterface
{
    public function fetch(int $limit = 12): array
    {
        $now = new \DateTimeImmutable();
        $items = [
            new FeedItem(
                id: 'mock:1',
                title: 'Highline session — Tisá',
                description: 'Pohodový víkend nad Tisou. Krátká lajna, dlouhej smích.',
                publishedAt: $now->modify('-2 days'),
                thumbnailUrl: 'https://placehold.co/640x360/e91e63/ffffff?text=Tis%C3%A1+highline',
                link: '#',
                source: 'youtube',
                authorName: 'czech_slackline',
                authorUrl: '#',
            ),
            new FeedItem(
                id: 'mock:2',
                title: 'První přechod Wild Sarah 2025',
                description: 'Klasika v Praze, 57 metrů, 40 metrů nad zemí.',
                publishedAt: $now->modify('-5 days'),
                thumbnailUrl: 'https://placehold.co/640x360/333333/ffffff?text=Wild+Sarah',
                link: '#',
                source: 'youtube',
                authorName: 'czech_slackline',
                authorUrl: '#',
            ),
            new FeedItem(
                id: 'mock:3',
                title: '#czechslackline meet 2025',
                description: 'Komunitní sraz v Ostrově. Padesát lidí, deset lajn, jeden páteční déšť.',
                publishedAt: $now->modify('-10 days'),
                thumbnailUrl: 'https://placehold.co/640x360/666666/ffffff?text=Komunitn%C3%AD+meet',
                link: '#',
                source: 'youtube',
                authorName: 'czech_slackline',
                authorUrl: '#',
            ),
        ];

        return array_slice($items, 0, $limit);
    }
}
