<?php

namespace Daugt\Commerce\Console\Commands;

use Daugt\Commerce\Console\AsciiArt;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Artisan;
use Statamic\Console\RunsInPlease;
use Stripe\StripeClient;

class InstallCommand extends Command {
    use RunsInPlease;


    protected $signature = 'statamic:daugt-commerce:install';

    protected $description = 'Installs Commerce Addon.';

    public function handle(): void
    {
        $this->output->write((new AsciiArt())());

        if (! $this->ensureStripeSecretConfigured()) {
            return;
        }

        Artisan::call('statamic:daugt-commerce:fetch-stripe-tax-codes');
    }

    private function ensureStripeSecretConfigured(): bool
    {
        if (config('statamic.daugt-commerce.stripe.secret')) {
            return true;
        }

        $this->warn('STRIPE_SECRET is not set. Set it in your .env and re-run this command.');
        return false;
    }

}
