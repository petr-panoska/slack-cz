<?php

namespace App\Enum;

enum Gender: string
{
    case Male = 'M';
    case Female = 'F';

    public function label(): string
    {
        return match ($this) {
            self::Male => 'Muž',
            self::Female => 'Žena',
        };
    }

    public static function fromLegacy(?string $value): ?self
    {
        if ($value === null) {
            return null;
        }
        return match (strtoupper(trim($value))) {
            'M' => self::Male,
            'F', 'Ž', 'Z' => self::Female,
            default => null,
        };
    }
}
