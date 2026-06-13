<?php

namespace App\Feed\Tv;

/**
 * One configured slackTV source — a channel ref (@handle / UC… id) or a playlist
 * id, plus whether its slider runs oldest-first. Built from a feed.tv.channels /
 * feed.tv.playlists entry, which is either a bare id string (newest-first, the
 * default) or a map {id: …, sort: asc|desc} — asc = oldest → newest.
 */
final readonly class TvSource
{
    public function __construct(
        public string $id,
        public bool $oldestFirst = false,
    ) {
    }

    /**
     * @param string|array{id?: string, sort?: string} $config
     */
    public static function fromConfig(string|array $config): self
    {
        if (is_string($config)) {
            return new self($config);
        }

        return new self(
            (string) ($config['id'] ?? ''),
            strtolower((string) ($config['sort'] ?? 'desc')) === 'asc',
        );
    }
}
