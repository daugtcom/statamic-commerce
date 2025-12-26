<?php

namespace Daugt\Commerce\Jobs;

use Daugt\Commerce\Payments\DTO\CustomerSyncData;
use Daugt\Commerce\Payments\PaymentProviderResolver;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Statamic\Facades\User;

class SyncPaymentCustomer implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function __construct(private string $userId)
    {
    }

    public function handle(PaymentProviderResolver $resolver): void
    {
        $user = User::find($this->userId);
        if (! $user) {
            return;
        }

        $store = $resolver->store();
        $provider = $resolver->provider();
        $customerId = $store->getCustomerId($user);

        $customerData = new CustomerSyncData(
            $user->id(),
            $user->email(),
            $user->name(),
            [
                'statamic_user_id' => $user->id(),
            ]
        );

        $result = $provider->syncCustomer($customerData, $customerId);

        $store->setCustomerId($user, $result->customerId);
    }
}
