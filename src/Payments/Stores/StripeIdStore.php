<?php

namespace Daugt\Commerce\Payments\Stores;

use Daugt\Commerce\Entries\ProductEntry;
use Daugt\Commerce\Payments\Contracts\PaymentIdStore;
use Statamic\Contracts\Auth\User;

class StripeIdStore implements PaymentIdStore
{
    public function getProductIds(ProductEntry $entry): array
    {
        return [
            'product_id' => $entry->stripeProductId(),
            'price_id' => $entry->stripePriceId(),
        ];
    }

    public function setProductIds(ProductEntry $entry, ?string $productId, ?string $priceId): void
    {
        $changed = false;

        if ($entry->stripeProductId() !== $productId) {
            $entry->set(ProductEntry::STRIPE_PRODUCT_ID, $productId);
            $changed = true;
        }

        if ($entry->stripePriceId() !== $priceId) {
            $entry->set(ProductEntry::STRIPE_PRICE_ID, $priceId);
            $changed = true;
        }

        if ($changed) {
            $entry->saveQuietly();
        }
    }

    public function getCustomerId(User $user): ?string
    {
        $value = $user->get('stripe_id');

        return $value !== null ? (string) $value : null;
    }

    public function setCustomerId(User $user, ?string $customerId): void
    {
        if ($this->getCustomerId($user) === $customerId) {
            return;
        }

        $user->set('stripe_id', $customerId);
        $user->saveQuietly();
    }
}
