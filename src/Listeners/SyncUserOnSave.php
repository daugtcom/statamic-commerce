<?php

namespace Daugt\Commerce\Listeners;

use Daugt\Commerce\Jobs\SyncPaymentCustomer;
use Statamic\Events\UserSaved;

class SyncUserOnSave
{
    public function handle(UserSaved $event): void
    {
        $user = $event->user;

        if (! $user) {
            return;
        }

        SyncPaymentCustomer::dispatch($user->id());
    }
}
