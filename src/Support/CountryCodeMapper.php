<?php

namespace Daugt\Commerce\Support;

use Statamic\Facades\Dictionary;

final class CountryCodeMapper
{
    private static ?array $iso3ToIso2 = null;

    public static function toIso2(?string $code): string
    {
        $code = strtoupper(trim((string) $code));

        if ($code === '') {
            return '';
        }

        if (strlen($code) === 2) {
            return $code;
        }

        $map = self::iso3ToIso2();

        return $map[$code] ?? $code;
    }

    public static function normalizeList(array $codes): array
    {
        $normalized = array_map(fn ($code) => self::toIso2(is_string($code) ? $code : null), $codes);
        $normalized = array_filter($normalized, fn ($code) => is_string($code) && strlen($code) === 2);

        return array_values(array_unique($normalized));
    }

    private static function iso3ToIso2(): array
    {
        if (self::$iso3ToIso2 !== null) {
            return self::$iso3ToIso2;
        }

        $dictionary = Dictionary::find('countries');

        if (! $dictionary) {
            self::$iso3ToIso2 = [];
            return self::$iso3ToIso2;
        }

        $map = [];

        foreach ($dictionary->optionItems() as $item) {
            $iso3 = strtoupper((string) $item->value());
            $iso2 = strtoupper((string) ($item['iso2'] ?? ''));

            if ($iso3 !== '' && $iso2 !== '') {
                $map[$iso3] = $iso2;
            }
        }

        self::$iso3ToIso2 = $map;

        return self::$iso3ToIso2;
    }
}
