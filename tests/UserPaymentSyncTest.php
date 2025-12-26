<?php

namespace Daugt\Commerce\Tests;

use Daugt\Commerce\Jobs\SyncPaymentCustomer;
use Daugt\Commerce\Payments\PaymentProviderResolver;
use Statamic\Facades\User;

class UserPaymentSyncTest extends TestCase
{
    public function test_creates_dummy_customer_id(): void
    {
        $user = User::make()
            ->email('user@example.test');

        $user->set('name', 'Test User');
        $user->saveQuietly();

        $this->runSync($user->id());

        $resolver = $this->app->make(PaymentProviderResolver::class);
        $this->assertSame("dummy_cus_{$user->id()}", $resolver->store()->getCustomerId($user));
    }

    public function test_keeps_existing_customer_id(): void
    {
        $user = User::make()
            ->email('updated@example.test');

        $user->set('name', 'Updated User');
        $user->saveQuietly();

        $resolver = $this->app->make(PaymentProviderResolver::class);
        $resolver->store()->setCustomerId($user, 'dummy_cus_existing');

        $this->runSync($user->id());

        $this->assertSame('dummy_cus_existing', $resolver->store()->getCustomerId($user));
    }

    private function runSync(string $userId): void
    {
        $resolver = $this->app->make(PaymentProviderResolver::class);
        (new SyncPaymentCustomer($userId))->handle($resolver);
    }
}
