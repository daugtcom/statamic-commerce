<script setup>
    import { computed } from 'vue';
    import { Modal, Button, Listing, Label } from '@statamic/cms/ui';
    import StatusBadge from '../components/StatusBadge.vue';
    import ShippingStatusBadge from '../components/ShippingStatusBadge.vue';

    const props = defineProps({
        open: { type: Boolean, default: false },
        order: { type: Object, default: null },
    });

    const emit = defineEmits(['update:open']);

    const itemColumns = [
        { field: 'product', label: __('daugt-commerce::orders.widget.modal.product'), sortable: false },
        { field: 'quantity', label: __('daugt-commerce::orders.widget.modal.quantity'), sortable: false },
        { field: 'shipping_status', label: __('daugt-commerce::orders.widget.modal.shipping_status'), sortable: false },
    ];

    const invoiceColumns = [
        { field: 'number', label: __('daugt-commerce::orders.widget.modal.invoice_number'), sortable: false },
        { field: 'status', label: __('daugt-commerce::orders.widget.columns.status'), sortable: false },
    ];

    const modalTitle = computed(() => {
        if (!props.order) {
            return __('daugt-commerce::orders.widget.modal.title');
        }

        const number = props.order.order_number ?? '—';
        return `${__('daugt-commerce::orders.widget.modal.title')} #${number}`;
    });

    const items = computed(() => props.order?.items ?? []);
    const invoices = computed(() => props.order?.invoices ?? []);
</script>

<template>
    <Modal :open="open" :title="modalTitle" @update:open="emit('update:open', $event)">
        <div class="space-y-4">
            <div class="grid grid-cols-2 gap-4 text-sm">
                <div>
                    <Label :text="__('daugt-commerce::orders.widget.columns.status')" />
                    <div>
                        <StatusBadge :status="order?.status" context="order" />
                    </div>
                </div>
                <div>
                    <Label :text="__('daugt-commerce::orders.widget.columns.succeeded_at')" />
                    <div>{{ order?.succeeded_at }}</div>
                </div>
            </div>

            <div>
                <Label class="mb-2" :text="__('daugt-commerce::orders.widget.modal.items')" />
                <div v-if="items.length === 0" class="text-sm text-gray-500">
                    {{ __('daugt-commerce::orders.widget.modal.items_empty') }}
                </div>
                <Listing
                    v-else
                    :allowSearch="false"
                    :allowPresets="false"
                    :allowBulkActions="false"
                    :sortable="false"
                    :allowCustomizingColumns="false"
                    :items="items"
                    :columns="itemColumns"
                >
                    <template #cell-product="{ row }">
                        <div class="flex items-center gap-2">
                            <img
                                v-if="row.media"
                                :src="row.media"
                                :alt="row.product ?? ''"
                                class="h-8 w-8 rounded object-cover bg-gray-100"
                                loading="lazy"
                            />
                            <span>{{ row.product ?? '—' }}</span>
                        </div>
                    </template>
                    <template #cell-shipping_status="{ row }">
                        <ShippingStatusBadge :status="row.shipping_status" />
                    </template>
                </Listing>
            </div>

            <div>
                <Label class="mb-2" :text="__('daugt-commerce::orders.widget.modal.invoices')" />
                <div v-if="invoices.length === 0" class="text-sm text-gray-500">
                    {{ __('daugt-commerce::orders.widget.modal.invoices_empty') }}
                </div>
                <Listing
                    v-else
                    :allowSearch="false"
                    :allowPresets="false"
                    :allowBulkActions="false"
                    :sortable="false"
                    :allowCustomizingColumns="false"
                    :items="invoices"
                    :columns="invoiceColumns"
                >
                    <template #cell-status="{ row }">
                        <StatusBadge :status="row.status" context="invoice" />
                    </template>
                </Listing>
            </div>
        </div>

        <template #footer>
            <div class="flex justify-end pt-2">
                <Button
                    variant="ghost"
                    :text="__('daugt-commerce::orders.widget.actions.close')"
                    @click="emit('update:open', false)"
                />
            </div>
        </template>
    </Modal>
</template>
