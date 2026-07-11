<?php

namespace App\Support;

/**
 * The frontend contract represents every fare/amount as a pair — an
 * integer kobo value (for exact arithmetic) plus a pre-formatted display
 * string — rather than a single decimal.
 */
class Money
{
    /**
     * @return array{kobo: int, display: string}
     */
    public static function fromNaira(float|string $naira): array
    {
        $naira = (float) $naira;

        return [
            'kobo' => (int) round($naira * 100),
            'display' => '₦'.number_format($naira),
        ];
    }
}
