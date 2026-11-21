<script setup>
import { computed } from 'vue';

const props = defineProps({
    kind: { type: String, default: 'active' },
    value: { type: [Boolean, String], default: true },
});

const display = computed(() => {
    if (props.kind === 'stock') {
        const map = {
            tersedia: ['badge-success', 'Tersedia'],
            menipis: ['badge-info', 'Menipis'],
            habis: ['badge-danger', 'Habis'],
        };

        return map[props.value] ?? ['badge-muted', String(props.value)];
    }

    return props.value ? ['badge-success', 'Aktif'] : ['badge-muted', 'Nonaktif'];
});
</script>

<template>
    <span class="badge" :class="display[0]">{{ display[1] }}</span>
</template>
