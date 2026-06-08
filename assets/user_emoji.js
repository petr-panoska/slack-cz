// A lineman is a living, moving creature — trees, rocks and rockets don't walk a
// highline. So the marker palette is animals only. Shared by the full /mapa map
// (map_controller) and the homepage hero map (hp_map_controller) so the same user
// always maps to the same creature in both places.
//
// Indices 0–29 are kept identical to the original palette so existing users keep
// their creature; only the old object/plant slots (30–49) became animals.
export const USER_EMOJIS = [
    '🦊', '🐻', '🐼', '🐨', '🐯', '🦁', '🐮', '🐷', '🐸', '🐵',
    '🐔', '🐧', '🦅', '🦉', '🐺', '🦄', '🐝', '🦋', '🐢', '🐠',
    '🐬', '🦈', '🦒', '🦓', '🦌', '🐕', '🐈', '🐇', '🦔', '🦝',
    '🐗', '🐴', '🐘', '🦏', '🦛', '🦘', '🦦', '🦥', '🐙', '🦀',
    '🐳', '🐡', '🦆', '🦢', '🦜', '🐞', '🐌', '🦎', '🐍', '🐦',
];

export function emojiForUser(userId) {
    return USER_EMOJIS[userId % USER_EMOJIS.length];
}
