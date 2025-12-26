<?php

namespace Daugt\Commerce\Listeners;

use Daugt\Commerce\Payments\Contracts\PaymentProviderExtension;
use Daugt\Commerce\Payments\PaymentProviderResolver;
use Statamic\Events\EntryBlueprintFound;
use Statamic\Events\UserBlueprintFound;

class ApplyPaymentProviderExtensions
{
    private ?array $extensions = null;

    public function __construct(private PaymentProviderResolver $resolver)
    {
    }

    public function handle(EntryBlueprintFound $event): void
    {
        $collectionHandle = $this->collectionHandle($event);
        if (! $collectionHandle) {
            return;
        }

        $active = $this->resolver->providerHandle();
        $this->removeEntryTabsForInactiveProviders($collectionHandle, $active, $event);

        $activeExtension = $this->activeExtension($active);
        if ($activeExtension) {
            $activeExtension->extendEntryBlueprint($collectionHandle, $event->blueprint);
        }
    }

    public function handleUser(UserBlueprintFound $event): void
    {
        $active = $this->resolver->providerHandle();
        $this->removeUserFieldsForInactiveProviders($active, $event);

        $activeExtension = $this->activeExtension($active);
        if ($activeExtension) {
            $activeExtension->extendUserBlueprint($event->blueprint);
        }
    }

    private function collectionHandle(EntryBlueprintFound $event): ?string
    {
        $entry = $event->entry;
        if ($entry && method_exists($entry, 'collectionHandle')) {
            return $entry->collectionHandle();
        }

        $handle = $event->blueprint->handle();
        if (! $handle) {
            return null;
        }

        $parts = explode('/', $handle);
        if (count($parts) >= 2 && $parts[0] === 'collections') {
            return $parts[1];
        }

        return null;
    }

    private function activeExtension(string $handle): ?PaymentProviderExtension
    {
        $providers = config('statamic.daugt-commerce.payment.providers', []);
        $definition = $providers[$handle] ?? null;

        if (! is_array($definition)) {
            return null;
        }

        $extensionClass = $definition['extension'] ?? null;
        if (! $extensionClass) {
            return null;
        }

        $extension = app($extensionClass);

        return $extension instanceof PaymentProviderExtension ? $extension : null;
    }

    private function removeEntryTabsForInactiveProviders(
        string $collectionHandle,
        string $active,
        EntryBlueprintFound $event
    ): void {
        $providers = config('statamic.daugt-commerce.payment.providers', []);

        foreach ($providers as $handle => $definition) {
            if ($handle === $active) {
                continue;
            }

            $extensionClass = $definition['extension'] ?? null;
            if (! $extensionClass || ! is_subclass_of($extensionClass, PaymentProviderExtension::class)) {
                continue;
            }

            $tabs = $extensionClass::entryTabsToRemove($collectionHandle);
            foreach ($tabs as $tab) {
                $event->blueprint->removeTab($tab);
            }
        }
    }

    private function removeUserFieldsForInactiveProviders(
        string $active,
        UserBlueprintFound $event
    ): void {
        $providers = config('statamic.daugt-commerce.payment.providers', []);

        foreach ($providers as $handle => $definition) {
            if ($handle === $active) {
                continue;
            }

            $extensionClass = $definition['extension'] ?? null;
            if (! $extensionClass || ! is_subclass_of($extensionClass, PaymentProviderExtension::class)) {
                continue;
            }

            $fields = $extensionClass::userFieldsToRemove();
            foreach ($fields as $field) {
                $event->blueprint->removeField($field);
            }
        }
    }
}
