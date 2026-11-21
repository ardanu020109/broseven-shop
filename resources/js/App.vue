<script setup>
import { onMounted } from 'vue';
import ToastHost from '@/components/common/ToastHost.vue';
import { loadBranding } from '@/composables/useBranding';

onMounted(() => {
    loadBranding();
});
</script>

<template>
    <RouterView />
    <ToastHost />
</template>
