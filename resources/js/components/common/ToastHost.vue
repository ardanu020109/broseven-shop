<script setup>
import { useToast } from '@/composables/useToast';

const { toasts, remove } = useToast();
</script>

<template>
    <TransitionGroup name="toast" tag="div" class="toast-host" aria-live="polite">
        <div
            v-for="toast in toasts"
            :key="toast.id"
            class="toast"
            :class="{ 'toast--error': toast.type === 'error' }"
            role="status"
            @click="remove(toast.id)"
        >
            {{ toast.message }}
        </div>
    </TransitionGroup>
</template>
