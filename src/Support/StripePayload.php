<?php

namespace Daugt\Commerce\Support;

use Illuminate\Support\Arr;

final class StripePayload
{
    public static function array(mixed $value, ?string $path = null): array
    {
        if ($path !== null) {
            $value = self::get($value, $path);
        }

        if (is_array($value)) {
            return $value;
        }

        if (is_object($value) && method_exists($value, 'toArray')) {
            return $value->toArray();
        }

        return [];
    }

    public static function string(mixed $value, ?string $path = null, string $default = ''): string
    {
        if ($path !== null) {
            $value = self::get($value, $path);
        }

        if (is_string($value)) {
            return $value;
        }

        if (is_numeric($value)) {
            return (string) $value;
        }

        if (is_array($value) && isset($value['id']) && (is_string($value['id']) || is_numeric($value['id']))) {
            return (string) $value['id'];
        }

        return $default;
    }

    public static function int(mixed $value, ?string $path = null): ?int
    {
        if ($path !== null) {
            $value = self::get($value, $path);
        }

        if (is_int($value)) {
            return $value;
        }

        if (is_numeric($value)) {
            return (int) $value;
        }

        return null;
    }

    public static function get(mixed $value, string $path, mixed $default = null): mixed
    {
        if (is_object($value) && method_exists($value, 'toArray')) {
            $value = $value->toArray();
        }

        return Arr::get($value, $path, $default);
    }
}
