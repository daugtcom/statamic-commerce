<?php

namespace Daugt\Commerce\Tests;

use Daugt\Commerce\Jobs\SyncStripeCustomer;
use Daugt\Commerce\Tests\Support\FakeStripeClient;
use Statamic\Facades\User;

class UserStripeSyncTest extends TestCase
{
    public function test_creates_stripe_customer_and_persists_id(): void
    {
        $user = User::make()
            ->email('user@example.test');

        $user->set('name', 'Test User');
        $user->saveQuietly();

        $fakeStripe = new FakeStripeClient();
        (new SyncStripeCustomer($user->id()))->handle($fakeStripe);

        $this->assertCount(1, $fakeStripe->customers->created);
        $this->assertSame('user@example.test', $fakeStripe->customers->created[0]['email']);

        $user = User::find($user->id());
        $this->assertNotNull($user->get('stripe_id'));
    }

    public function test_updates_existing_stripe_customer(): void
    {
        $user = User::make()
            ->email('updated@example.test');

        $user->set('name', 'Updated User');
        $user->set('stripe_id', 'cus_123');
        $user->saveQuietly();

        $fakeStripe = new FakeStripeClient();
        (new SyncStripeCustomer($user->id()))->handle($fakeStripe);

        $this->assertCount(0, $fakeStripe->customers->created);
        $this->assertCount(1, $fakeStripe->customers->updated);
        $this->assertSame('cus_123', $fakeStripe->customers->updated[0]['id']);
        $this->assertSame('updated@example.test', $fakeStripe->customers->updated[0]['payload']['email']);
    }
}
