<?php

namespace Daugt\Commerce\Support;

use Statamic\Facades\Addon;

final class AddonEdition
{
    public static function edition(): ?string
    {
        $addon = Addon::get('daugtcom/statamic-commerce');

        return $addon?->edition();
    }

    public static function isPro(): bool
    {
        return self::edition() === 'pro';
    }
}
