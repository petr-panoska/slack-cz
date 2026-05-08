<?php

namespace App\Feed;

final class MockFeedFetcher implements FeedFetcherInterface
{
    /**
     * Reálná videa z playlistu SLACKHOVOR od Philipa Bitnara
     * (https://www.youtube.com/playlist?list=PLoxabYJhgiYDSZr1c694xILPE_endi7FP).
     * Mock se renderuje jen když chybí YouTube API key nebo je vyčerpaná
     * kvóta a není last-known-good — chceme, aby panel i v tom režimu vypadal
     * jako reálný feed (kliknutelné odkazy, pořádné thumbnaily, ne placeholdery).
     */
    private const VIDEOS = [
        ['D3JYAiqJ2zI', 'SLACKHOVOR #31 w/ Petr Voříšek – Nový český slackline rekord 1 130 m'],
        ['XxtxKJ4_Vsw', 'SLACKHOVOR #30 w/ Jakub „Píďa" Trávník: „Kvůli highline jsem dělal paragliding."'],
        ['y27SlpGMMIg', 'SLACKHOVOR #29 w/ Jan „Jizva" Tattermusch: „Ještě bych rád přešel nějakou highline."'],
        ['pWP-GCMDTIw', 'SLACKHOVOR #28 w/ Marek Smolka (Gibbon Slacklines CZ)'],
        ['rUwYerHVYYs', 'SLACKHOVOR #27 s Jiřím Cerhanem o trickline, Strojírně a rýžování zlata'],
        ['I9IrBK0kXWI', 'SLACKHOVOR #26 Bára Kubátová: Jako tricklajneři pomalu vymíráme'],
        ['pvZd2j7_Qq4', 'SLACKHOVOR #25 s Equilibrium Slacklines // Jakub Hanuš & Jakub Dostál'],
        ['I2OkLcrMrOU', 'SLACKHOVOR #24 s Uhlíkem: „Na slackline je hlava to nejvíc"'],
        ['86TEGI3GLFw', 'SLACKHOVOR #23: S Matym o highline, hudbě a deskových hrách'],
        ['tTe6NFJ5rC4', 'SLACKHOVOR #22: S Jankou o lajnách, Wood Slacku, asociaci a hudbě'],
        ['rsTBPu2NQBQ', 'PHILIP_SlackHovor vol. 21 w/ Saša Kasal (+ENG subtitles)'],
        ['iloWDMSDXDc', 'PHILIP_SlackHovor vol. 20 w/ Jan Halfar (+ENG subtitles)'],
    ];

    private const AUTHOR_NAME = 'Philip Bitnar';
    private const AUTHOR_URL = 'https://www.youtube.com/@PhilipBitnar';

    public function fetch(int $limit = 12): array
    {
        $now = new \DateTimeImmutable();
        $items = [];
        foreach (self::VIDEOS as $i => [$videoId, $title]) {
            $items[] = new FeedItem(
                id: 'yt:' . $videoId,
                title: $title,
                description: null,
                publishedAt: $now->modify('-' . (3 + $i * 30) . ' days'),
                thumbnailUrl: 'https://i.ytimg.com/vi/' . $videoId . '/hqdefault.jpg',
                link: 'https://www.youtube.com/watch?v=' . $videoId,
                source: 'youtube',
                authorName: self::AUTHOR_NAME,
                authorUrl: self::AUTHOR_URL,
            );
        }

        return array_slice($items, 0, $limit);
    }
}
