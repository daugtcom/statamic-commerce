<?php

namespace Daugt\Commerce\Support;

use Statamic\Addons\Addon as StatamicAddon;
use Statamic\Facades\Addon;

class AddonSettings
{
    private static ?StatamicAddon $addon = null;

    public static function get(string $key): mixed
    {
        $addon = self::addon();

        if (! $addon || ! $addon->hasSettingsBlueprint()) {
            return null;
        }

        return $addon->setting($key);
    }

    public static function firstValue(string $key): ?string
    {
        $value = self::get($key);

        if (is_array($value)) {
            return $value[0] ?? null;
        }

        if (is_string($value) && $value !== '') {
            return $value;
        }

        return null;
    }

    public static function set(string|array $key, mixed $value = null): void
    {
        $addon = self::addon();

        if (! $addon) {
            return;
        }

        $settings = $addon->settings();
        $settings->set($key, $value);
        $settings->save();
    }

    public static function reset(): void
    {
        self::$addon = null;
    }

    private static function addon(): ?StatamicAddon
    {
        if (self::$addon !== null) {
            return self::$addon;
        }

        self::$addon = Addon::get('daugtcom/statamic-commerce');

        return self::$addon;
    }
}
