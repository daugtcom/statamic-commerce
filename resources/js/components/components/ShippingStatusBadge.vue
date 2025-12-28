<script setup>
import { computed } from 'vue';
import { Badge } from '@statamic/cms/ui';

const props = defineProps({
    status: { type: [String, null], default: null },
});

const normalizedStatus = computed(() => (props.status ?? '').toString().trim().toLowerCase());
const hasStatus = computed(() => normalizedStatus.value !== '');

const label = computed(() => {
    if (!hasStatus.value) {
        return '-';
    }

    const key = `daugt-commerce::shipping-statuses.${normalizedStatus.value}`;
    const translated = __(key);
    return translated === key ? normalizedStatus.value : translated;
});

const color = computed(() => {
    switch (normalizedStatus.value) {
        case 'delivered':
            return 'green';
        case 'shipped':
            return 'blue';
        case 'pending':
            return 'amber';
        default:
            return 'default';
    }
});
</script>

<template>
    <Badge v-if="hasStatus" :text="label" :color="color" size="sm" />
    <span v-else class="text-sm text-gray-500">-</span>
</template>
