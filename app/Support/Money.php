<?php

namespace App\Support;

use InvalidArgumentException;

/**
 * All money in the application is stored as integer nanodollars (1e-9 USD).
 * Pixelforge reports costs with nine decimal places, so this scale represents
 * every provider amount exactly without floating point drift.
 */
final class Money
{
    public const NANO_PER_DOLLAR = 1_000_000_000;

    private const BILLED_TOKEN_TYPES = [
        'input_text_tokens',
        'input_image_tokens',
        'output_tokens',
        'reasoning_tokens',
    ];

    private function __construct() {}

    /**
     * Convert an exact decimal string such as "0.046125000" to nanodollars.
     */
    public static function fromDecimal(string $amount): int
    {
        $pattern = '/^(-)?(\d+)(?:\.(\d{1,9}))?$/';

        if (preg_match($pattern, trim($amount), $matches) !== 1) {
            throw new InvalidArgumentException("Invalid money amount [{$amount}].");
        }

        $whole = (int) $matches[2];

        if ($whole > intdiv(PHP_INT_MAX, self::NANO_PER_DOLLAR)) {
            throw new InvalidArgumentException("Money amount [{$amount}] is too large.");
        }

        $fraction = str_pad($matches[3] ?? '', 9, '0');
        $nano = ($whole * self::NANO_PER_DOLLAR) + (int) $fraction;

        return ($matches[1] ?? '') === '-' ? -$nano : $nano;
    }

    /**
     * Convert a Pixelforge token count and rate (USD per 1M tokens) to nanodollars.
     */
    public static function costForTokens(int $tokens, string $ratePerMillion): int
    {
        $rateNano = self::fromDecimal($ratePerMillion);

        return intdiv(($tokens * $rateNano) + 500_000, 1_000_000);
    }

    /**
     * Calculate the provider's cost for a completed generation's usage.
     *
     * @param  array<string, int|string>  $usage
     * @param  array<string, string>  $rates
     */
    public static function providerCost(array $usage, array $rates): int
    {
        $total = 0;

        foreach (self::BILLED_TOKEN_TYPES as $tokenType) {
            if (! array_key_exists($tokenType, $rates)) {
                throw new InvalidArgumentException("Missing rate for [{$tokenType}].");
            }

            $total += self::costForTokens((int) ($usage[$tokenType] ?? 0), (string) $rates[$tokenType]);
        }

        return $total;
    }

    /**
     * Apply a whole-percent markup to a nanodollar amount, rounded half up.
     */
    public static function withMarkup(int $providerCostNano, int $markupPercent): int
    {
        return intdiv(($providerCostNano * (100 + $markupPercent)) + 50, 100);
    }

    /**
     * Format nanodollars for display. Trailing zeroes are trimmed, but values
     * always show cents. The default six decimals keeps sub-cent costs legible.
     */
    public static function format(int $nano, int $maxDecimals = 6): string
    {
        $sign = $nano < 0 ? '-' : '';
        $absolute = abs($nano);
        $maxDecimals = max(2, min(9, $maxDecimals));
        $roundTo = 10 ** (9 - $maxDecimals);
        $rounded = intdiv($absolute + intdiv($roundTo, 2), $roundTo) * $roundTo;

        $whole = intdiv($rounded, self::NANO_PER_DOLLAR);
        $fraction = str_pad((string) ($rounded % self::NANO_PER_DOLLAR), 9, '0', STR_PAD_LEFT);
        $fraction = rtrim(substr($fraction, 0, $maxDecimals), '0');
        $fraction = str_pad($fraction, 2, '0');

        return "{$sign}\${$whole}.{$fraction}";
    }
}
