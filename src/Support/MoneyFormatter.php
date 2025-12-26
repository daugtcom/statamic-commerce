<?php

namespace Daugt\Commerce\Support;

class MoneyFormatter
{
    public static function format(float $amount, string $currency, string $locale): string
    {
        $currency = strtoupper($currency);

        if (class_exists(\NumberFormatter::class)) {
            $formatter = new \NumberFormatter($locale, \NumberFormatter::CURRENCY);
            $formatted = $formatter->formatCurrency($amount, $currency);

            if ($formatted !== false) {
                return $formatted;
            }
        }

        $useComma = str_starts_with($locale, 'de');
        $decimal = $useComma ? ',' : '.';
        $thousands = $useComma ? '.' : ',';
        $value = number_format($amount, 2, $decimal, $thousands);

        return self::symbol($currency) . $value;
    }

    private static function symbol(string $currency): string
    {
        return match ($currency) {
            'EUR' => '€',
            'USD' => '$',
            'GBP' => '£',
            default => $currency . ' ',
        };
    }
}
