<?php

namespace Daugt\Commerce\Services;

use Carbon\Carbon;
use Daugt\Commerce\Entries\OrderEntry;
use Daugt\Commerce\Entries\ProductEntry;
use Daugt\Commerce\Enums\AccessType;
use Daugt\Commerce\Enums\BillingUnit;
use Daugt\Commerce\Enums\OrderStatus;
use Daugt\Access\Entries\EntitlementEntry;
use Daugt\Access\Services\AccessService;
use Illuminate\Support\Arr;
use Statamic\Facades\Collection as CollectionFacade;
use Statamic\Facades\Entry;

class OrderEntitlementService
{
    private const ORDER_REFERENCE = 'order';

    public function syncForOrder(OrderEntry $order): void
    {
        if (! $this->canUseAccess()) {
            return;
        }

        $this->revokeForOrder($order);

        if ($order->status() !== OrderStatus::PAID->value) {
            return;
        }

        $userId = $order->userId();
        if (! $userId) {
            return;
        }

        $accessItems = $this->accessItemsForOrder($order);
        if ($accessItems === []) {
            return;
        }

        $accessService = app(AccessService::class);

        foreach ($accessItems as $item) {
            $entitlement = $accessService->grantEntitlement(
                $userId,
                $item['target_id'],
                $item['start'],
                $item['end'],
                false,
                true
            );

            $entitlement->set(self::ORDER_REFERENCE, (string) $order->id());
            $entitlement->saveQuietly();
        }
    }

    public function revokeForOrder(OrderEntry $order): void
    {
        if (! $this->canUseAccess()) {
            return;
        }

        $orderId = (string) $order->id();
        if ($orderId === '') {
            return;
        }

        $entitlements = Entry::query()
            ->where('collection', EntitlementEntry::COLLECTION)
            ->where(self::ORDER_REFERENCE, $orderId)
            ->get();

        if ($entitlements->isEmpty()) {
            return;
        }

        $accessService = app(AccessService::class);

        $entitlements->each(function ($entitlement) use ($accessService) {
            $accessService->revokeEntitlement($entitlement);
        });
    }

    private function canUseAccess(): bool
    {
        if (! class_exists(AccessService::class) || ! class_exists(EntitlementEntry::class)) {
            return false;
        }

        return CollectionFacade::find(EntitlementEntry::COLLECTION) !== null;
    }

    private function accessItemsForOrder(OrderEntry $order): array
    {
        $items = $order->get(OrderEntry::ITEMS);

        if (! is_array($items) || $items === []) {
            return [];
        }

        $targets = [];

        foreach ($items as $item) {
            if (! is_array($item)) {
                continue;
            }

            $productId = $this->firstId($item['product'] ?? null);
            if (! $productId) {
                continue;
            }

            $product = Entry::find($productId);
            if (! $product || $product->collectionHandle() !== ProductEntry::COLLECTION) {
                continue;
            }

            if ((bool) $product->get(ProductEntry::EXTERNAL_PRODUCT)) {
                continue;
            }

            $accessItems = $product->get(ProductEntry::ALL_ACCESS_ITEMS);
            if (! is_array($accessItems)) {
                continue;
            }

            foreach ($accessItems as $accessItem) {
                if (! is_array($accessItem)) {
                    continue;
                }

                if (array_key_exists('enabled', $accessItem) && ! $accessItem['enabled']) {
                    continue;
                }

                $type = (string) ($accessItem['type'] ?? '');
                if ($type === '') {
                    continue;
                }

                $targetId = $this->firstId($accessItem[$type] ?? null);
                if (! $targetId) {
                    continue;
                }

                [$start, $end] = $this->resolveValidity($accessItem);
                $key = $this->dedupeKey($targetId, $start, $end);

                if (isset($targets[$key])) {
                    continue;
                }

                $targets[$key] = [
                    'target_id' => $targetId,
                    'start' => $start,
                    'end' => $end,
                ];
            }
        }

        return array_values($targets);
    }

    private function resolveValidity(array $accessItem): array
    {
        $typeValue = (string) ($accessItem['access_type'] ?? AccessType::PERMANENT->value);
        $type = AccessType::tryFrom($typeValue) ?? AccessType::PERMANENT;

        return match ($type) {
            AccessType::DATE_RANGE => $this->rangeFromValue($accessItem['date_range'] ?? null),
            AccessType::DURATION => $this->durationFromValue($accessItem),
            AccessType::PERMANENT => [null, null],
        };
    }

    private function rangeFromValue(mixed $value): array
    {
        $start = $this->parseDateValue(Arr::get($value, 'start', Arr::get($value, 'from', $value)));
        $end = $this->parseDateValue(Arr::get($value, 'end', Arr::get($value, 'to', null)));

        return [$start, $end];
    }

    private function durationFromValue(array $accessItem): array
    {
        $start = now();
        $iterations = (int) ($accessItem['access_duration_iterations'] ?? 1);
        if ($iterations < 1) {
            $iterations = 1;
        }

        $unit = (string) ($accessItem['access_duration_unit'] ?? BillingUnit::MONTH->value);
        $end = $this->addDuration($start->copy(), $iterations, $unit);

        return [$start, $end];
    }

    private function addDuration(Carbon $start, int $iterations, string $unit): Carbon
    {
        return match ($unit) {
            BillingUnit::YEAR->value => $start->addYears($iterations),
            BillingUnit::MONTH->value => $start->addMonths($iterations),
            BillingUnit::WEEK->value => $start->addWeeks($iterations),
            BillingUnit::DAY->value => $start->addDays($iterations),
            BillingUnit::HOUR->value => $start->addHours($iterations),
            default => $start->addMonths($iterations),
        };
    }

    private function parseDateValue(mixed $value): ?Carbon
    {
        if (! $value) {
            return null;
        }

        if ($value instanceof \DateTimeInterface) {
            return Carbon::instance($value);
        }

        if (is_array($value)) {
            $nested = Arr::get($value, 'date') ?? Arr::get($value, 'value');

            return $nested ? Carbon::parse($nested) : null;
        }

        if (is_string($value)) {
            return Carbon::parse($value);
        }

        return null;
    }

    private function firstId(mixed $value): ?string
    {
        if (is_array($value)) {
            $value = $value[0] ?? null;
        }

        if (is_string($value) && $value !== '') {
            return $value;
        }

        return null;
    }

    private function dedupeKey(string $targetId, ?Carbon $start, ?Carbon $end): string
    {
        return implode('|', [
            $targetId,
            $start?->toDateTimeString() ?? '',
            $end?->toDateTimeString() ?? '',
        ]);
    }
}
