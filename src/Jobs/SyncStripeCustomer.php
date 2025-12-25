<?php

namespace Daugt\Commerce\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Statamic\Facades\User;
use Stripe\StripeClient;

class SyncStripeCustomer implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function __construct(private string $userId)
    {
    }

    public function handle(StripeClient $stripeClient): void
    {
        $user = User::find($this->userId);
        if (! $user) {
            return;
        }

        $stripeId = $user->get('stripe_id');

        $payload = [
            'metadata' => [
                'statamic_user_id' => $user->id(),
            ],
        ];

        $email = $user->email();
        if ($email) {
            $payload['email'] = $email;
        }

        $name = $user->name();
        if ($name) {
            $payload['name'] = $name;
        }

        if ($stripeId) {
            $stripeClient->customers->update($stripeId, $payload);
            return;
        }

        $customer = $stripeClient->customers->create($payload);
        if (! $customer?->id) {
            return;
        }

        $user->set('stripe_id', $customer->id);
        $user->saveQuietly();
    }
}
