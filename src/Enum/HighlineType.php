<?php

namespace App\Enum;

enum HighlineType: string
{
    case Unsorted = 'unsorted';
    case Highline = 'highline';
    case Midline = 'midline';
    case Longline = 'longline';
    case Waterline = 'waterline';

    public function label(): string
    {
        return match ($this) {
            self::Unsorted => 'Nezařazeno',
            self::Highline => 'Highline',
            self::Midline => 'Midline',
            self::Longline => 'Longline',
            self::Waterline => 'Waterline',
        };
    }

    public static function fromLegacyId(int $id): self
    {
        // Legacy "Top Highline" (2) and "Urban Line" (4) collapse into Highline.
        return match ($id) {
            1, 2, 4 => self::Highline,
            3 => self::Midline,
            default => self::Unsorted,
        };
    }
}
