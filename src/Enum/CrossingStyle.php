<?php

namespace App\Enum;

/**
 * Crossing styles, shared by highline and longline deníky. See
 * docs/crossing-styles.md for definitions. SWAMI / SOLO / KOTNIK are
 * leash/protection styles that only apply to highline — see appliesToLongline().
 *
 * Important: OS_FM and OS_THEN_FM are different things — `OS, FM` (with comma)
 * means one direction OS, the other FM (clean but not first try). Don't merge them.
 */
enum CrossingStyle: string
{
    case OS_FM = 'os_fm';
    case OS_THEN_FM = 'os_fm_split';
    case OS = 'os';
    case FM = 'fm';
    case OW = 'ow';
    case AF = 'af';
    case SWAMI = 'swami';
    case SOLO = 'solo';
    case KOTNIK = 'kotnik';

    public function label(): string
    {
        return match ($this) {
            self::OS_FM => 'OS FM',
            self::OS_THEN_FM => 'OS, FM',
            self::OS => 'OS',
            self::FM => 'FM',
            self::OW => 'OW',
            self::AF => 'AF',
            self::SWAMI => 'swami',
            self::SOLO => 'solo',
            self::KOTNIK => 'kotník',
        };
    }

    /**
     * Whether this style makes sense for a longline (the /denik longline tab
     * reuses this enum). SWAMI / SOLO / KOTNIK describe the leash / protection,
     * which only highlines have — so they're highline-only and excluded from the
     * longline style picker.
     */
    public function appliesToLongline(): bool
    {
        return !in_array($this, [self::SWAMI, self::SOLO, self::KOTNIK], true);
    }

    /**
     * Map a legacy `styl` text to an enum case (or null for empty/unknown).
     */
    public static function fromLegacy(?string $value): ?self
    {
        if ($value === null) {
            return null;
        }
        $normalized = strtolower(trim($value));
        if ($normalized === '' || $normalized === '-') {
            return null;
        }
        return match ($normalized) {
            'os fm' => self::OS_FM,
            'os, fm' => self::OS_THEN_FM,
            'os' => self::OS,
            'fm' => self::FM,
            'one way', 'one w', 'ow' => self::OW,
            'af' => self::AF,
            'swami' => self::SWAMI,
            'solo' => self::SOLO,
            'kotnik', 'kotník' => self::KOTNIK,
            default => null,
        };
    }
}
