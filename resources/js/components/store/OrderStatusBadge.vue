<script setup>
import { computed } from 'vue';

const props = defineProps({
    status: { type: String, required: true },
    label: { type: String, default: '' },
});

const map = {
    DRAFT: ['badge-info', 'Draft'],
    MENUNGGU: ['badge-dark', 'Menunggu'],
    SELESAI: ['badge-success', 'Selesai'],
    DIBATALKAN: ['badge-danger', 'Dibatalkan'],
};

const display = computed(() => {
    const [klass, fallback] = map[props.status] ?? ['badge-muted', props.status];

    return { klass, text: props.label || fallback };
});
</script>

<template>
    <span class="badge" :class="display.klass">{{ display.text }}</span>
</template>
