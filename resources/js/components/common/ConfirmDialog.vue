<script setup>
import AppModal from '@/components/common/AppModal.vue';

defineProps({
    modelValue: { type: Boolean, default: false },
    title: { type: String, default: 'Konfirmasi' },
    message: { type: String, default: '' },
    confirmText: { type: String, default: 'Ya, Lanjutkan' },
    danger: { type: Boolean, default: false },
    loading: { type: Boolean, default: false },
});

const emit = defineEmits(['update:modelValue', 'confirm']);
</script>

<template>
    <AppModal
        :model-value="modelValue"
        :title="title"
        width="440px"
        :closable="!loading"
        @update:model-value="emit('update:modelValue', $event)"
    >
        <p style="margin: 0">{{ message }}</p>

        <template #footer>
            <button class="btn btn-outline" type="button" :disabled="loading" @click="emit('update:modelValue', false)">
                Batal
            </button>
            <button
                class="btn"
                :class="danger ? 'btn-danger' : 'btn-primary'"
                type="button"
                :disabled="loading"
                @click="emit('confirm')"
            >
                {{ loading ? 'Memproses...' : confirmText }}
            </button>
        </template>
    </AppModal>
</template>
