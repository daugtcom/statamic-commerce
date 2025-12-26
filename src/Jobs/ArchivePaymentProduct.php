<?php

namespace Daugt\Commerce\Jobs;

use Daugt\Commerce\Payments\PaymentProviderResolver;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class ArchivePaymentProduct implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function __construct(
        private ?string $productId,
        private ?string $priceId
    ) {
    }

    public function handle(PaymentProviderResolver $resolver): void
    {
        $resolver->provider()->archiveProduct($this->productId, $this->priceId);
    }
}
