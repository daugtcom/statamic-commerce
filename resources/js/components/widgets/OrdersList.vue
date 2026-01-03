<script setup>
    import { Widget, Listing, Button } from '@statamic/cms/ui';
    import { computed, ref, watch } from 'vue';
    import OrderDetailsModal from './OrderDetailsModal.vue';
    import StatusBadge from "../components/StatusBadge.vue";
    import ShippingStatusBadge from "../components/ShippingStatusBadge.vue";

    const props = defineProps({
        title: { type: String, default: 'Orders' },
        orders: { type: Array, default: () => [] },
    });

    const hasShippingStatus = computed(() => props.orders.some((order) => !!order.shipping_status));
    const columns = computed(() => {
        const base = [
            { field: 'order_number', label: __('daugt-commerce::orders.widget.columns.order_number'), sortable: false },
            { field: 'status', label: __('daugt-commerce::orders.widget.columns.status'), sortable: false },
        ];

        if (hasShippingStatus.value) {
            base.push({ field: 'shipping_status', label: __('daugt-commerce::orders.widget.columns.shipping_status'), sortable: false });
        }

        base.push(
            { field: 'succeeded_at', label: __('daugt-commerce::orders.widget.columns.succeeded_at'), sortable: false },
            { field: 'actions', label: '', sortable: false }
        );

        return base;
    });

    const modalOpen = ref(false);
    const selectedOrder = ref(null);

    const hasOrders = computed(() => props.orders.length > 0);

    function openDetails(order) {
        selectedOrder.value = order;
        modalOpen.value = true;
    }

    watch(modalOpen, (value) => {
        if (!value) {
            selectedOrder.value = null;
        }
    });
</script>

<template>
    <Widget :title="title">
        <div class="p-2">
            <div v-if="!hasOrders" class="text-sm text-gray-500 px-2 py-1">
                {{ __('daugt-commerce::orders.widget.empty') }}
            </div>

            <Listing
                v-else
                :allowSearch="false"
                :allowPresets="false"
                :allowBulkActions="false"
                :sortable="false"
                :allowCustomizingColumns="false"
                :items="orders"
                :columns="columns"
            >
                <template #cell-order_number="{ row }">
                    <span class="title-index-field">
                        #{{ row.order_number ?? '—' }}
                    </span>
                </template>

              <template #cell-status="{ row }">
                <StatusBadge :status="row.status" context="order" />
              </template>

                <template #cell-shipping_status="{ row }">
                    <ShippingStatusBadge v-if="row.shipping_status" :status="row.shipping_status" />
                </template>

                <template #cell-succeeded_at="{ row }">
                    <span>{{ row.succeeded_at }}</span>
                </template>

                <template #cell-actions="{ row }">
                    <Button
                        variant="ghost"
                        size="sm"
                        :text="__('daugt-commerce::orders.widget.actions.details')"
                        @click="openDetails(row)"
                    />
                </template>
            </Listing>
        </div>
    </Widget>

    <OrderDetailsModal
        v-if="selectedOrder"
        v-model:open="modalOpen"
        :order="selectedOrder"
    />
</template>
