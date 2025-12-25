<?php

namespace Daugt\Commerce\Dictionaries;

use Daugt\Commerce\Console\Commands\FetchStripeTaxCodes;
use Illuminate\Support\Facades\Cache;
use Statamic\Dictionaries\BasicDictionary;

class StripeTaxCodes extends BasicDictionary
{
    protected function getItems(): array
    {
        $items = Cache::get(FetchStripeTaxCodes::CACHE_KEY);

        if (is_array($items)) {
            return $items;
        }

        if (! config('statamic.daugt-commerce.stripe.secret')) {
            return [];
        }

        app(FetchStripeTaxCodes::class)->fetch(false);

        $items = Cache::get(FetchStripeTaxCodes::CACHE_KEY);

        return is_array($items) ? $items : [];
    }
}
