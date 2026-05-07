<?php

namespace App\Enum;

enum HighlineType: string
{
    case Unsorted = 'unsorted';
    case Highline = 'highline';
    case TopHighline = 'top_highline';
    case Midline = 'midline';
    case UrbanLine = 'urban_line';

    public function label(): string
    {
        return match ($this) {
            self::Unsorted => 'Nezařazeno',
            self::Highline => 'Highline',
            self::TopHighline => 'Top Highline',
            self::Midline => 'Midline',
            self::UrbanLine => 'Urban Line',
        };
    }

    public static function fromLegacyId(int $id): self
    {
        return match ($id) {
            1 => self::Highline,
            2 => self::TopHighline,
            3 => self::Midline,
            4 => self::UrbanLine,
            default => self::Unsorted,
        };
    }
}
