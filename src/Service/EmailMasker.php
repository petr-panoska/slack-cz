<?php

namespace App\Service;

final class EmailMasker
{
    public function mask(string $email): string
    {
        [$localPart, $domain] = array_pad(explode('@', $email, 2), 2, '');

        $visibleLocalPart = mb_substr($localPart, 0, 2);
        $visibleDomainStart = mb_substr($domain, 0, 1);
        $visibleDomainEnd = mb_strlen($domain) > 1 ? mb_substr($domain, -1) : '';

        return sprintf(
            '%s*****@%s****%s',
            $visibleLocalPart,
            $visibleDomainStart,
            $visibleDomainEnd,
        );
    }
}
