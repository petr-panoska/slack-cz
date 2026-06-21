<?php

namespace App\Enum;

/**
 * Lifecycle of a LineEdit row.
 *
 * - APPLIED: change is already on the highline (direct edit by owner of unverified, or by admin).
 * - PENDING: proposal queued for admin review (verified highline edited by non-admin).
 * - REJECTED: admin declined the proposal; kept as audit record.
 */
enum LineEditStatus: string
{
    case APPLIED = 'applied';
    case PENDING = 'pending';
    case REJECTED = 'rejected';

    public function label(): string
    {
        return match ($this) {
            self::APPLIED => 'aplikováno',
            self::PENDING => 'čeká na schválení',
            self::REJECTED => 'zamítnuto',
        };
    }
}
