<?php

namespace Daugt\Commerce\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Psr\SimpleCache\InvalidArgumentException;
use Statamic\Console\RunsInPlease;
use Stripe\Exception\ApiErrorException;
use Stripe\StripeClient;
use function Laravel\Prompts\info;
use function Laravel\Prompts\progress;
use function Laravel\Prompts\warning;

class FetchStripeTaxCodes extends Command {
    use RunsInPlease;

    public const CACHE_KEY = 'daugt-commerce:stripe-tax-codes';

    public const CACHE_TTL = 60 * 60 * 24 * 30;

    protected $signature = 'statamic:daugt-commerce:fetch-stripe-tax-codes';

    protected $description = 'Fetches Stripe tax codes';

    private Collection $taxCodes;

    public function __construct()
    {
        parent::__construct();
    }

    /**
     * @throws ApiErrorException
     * @throws InvalidArgumentException
     */
    public function handle(StripeClient $stripeClient) {
        $taxCodes = $this->fetch($stripeClient);

        if ($taxCodes->isEmpty()) {
            warning('0 tax codes fetched.');
            return;
        }

        info("{$taxCodes->count()} tax codes fetched.");
    }

    /**
     * @throws ApiErrorException
     * @throws InvalidArgumentException
     */
    public function fetch(?StripeClient $stripeClient = null, bool $withProgress = true): Collection
    {
        $this->taxCodes = collect();

        $stripeClient = $stripeClient ?? app(StripeClient::class);
        $page = $stripeClient->taxCodes->all(['limit' => 100]);

        if ($page->isEmpty()) {
            Cache::set(self::CACHE_KEY, [], self::CACHE_TTL);
            return $this->taxCodes;
        }

        $progress = null;
        if ($withProgress && app()->runningInConsole()) {
            $progress = progress('Fetching Stripe tax codes...', count($page->data));
            $progress->start();
        }

        while (! $page->isEmpty()) {
            if ($progress) {
                $pageCount = count($page->data);
                if ($progress->progress + $pageCount > $progress->total) {
                    $progress->total = $progress->progress + $pageCount;
                }
            }

            foreach ($page->data as $taxCode) {
                $this->persistTaxCode($taxCode);
                if ($progress) {
                    $progress->advance();
                }
            }

            $page = $page->nextPage();
        }

        if ($progress) {
            $progress->total = $this->taxCodes->count();
            $progress->finish();
        }

        Cache::set(self::CACHE_KEY, $this->taxCodes->map(fn ($taxCode) => [
            'value' => $taxCode->id,
            'label' => $taxCode->name,
        ])->all(), self::CACHE_TTL);

        return $this->taxCodes;
    }

    public function persistTaxCode($taxCode)
    {
        $this->taxCodes[] = $taxCode;
    }
}
