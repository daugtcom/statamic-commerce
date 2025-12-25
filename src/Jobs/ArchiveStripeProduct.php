<?php

namespace Daugt\Commerce\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Stripe\StripeClient;

class ArchiveStripeProduct implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function __construct(
        private ?string $stripeProductId,
        private ?string $stripePriceId
    ) {
    }

    public function handle(StripeClient $stripeClient): void
    {
        if (! $this->stripeProductId) {
            return;
        }

        if ($this->stripePriceId) {
            $stripeClient->prices->update($this->stripePriceId, [
                'active' => false,
            ]);
        }

        $stripeClient->products->update($this->stripeProductId, [
            'active' => false,
        ]);
    }
}
