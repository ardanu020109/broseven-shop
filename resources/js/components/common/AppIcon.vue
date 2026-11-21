<script setup>
import { icons } from '@/lib/icons';
import { extraIcons } from '@/lib/iconsExtra';

defineProps({
    name: { type: String, required: true },
    size: { type: Number, default: 20 },
});

const all = { ...icons, ...extraIcons };
</script>

<template>
    <svg
        :width="size"
        :height="size"
        viewBox="0 0 24 24"
        fill="none"
        stroke="currentColor"
        stroke-width="2"
        stroke-linecap="round"
        stroke-linejoin="round"
        aria-hidden="true"
        v-html="all[name] ?? ''"
    />
</template>
