<script setup>
import { computed, ref, watch } from 'vue';
import AppModal from '@/components/common/AppModal.vue';

const props = defineProps({
    modelValue: { type: Boolean, default: false },
    order: { type: Object, default: null },
    loading: { type: Boolean, default: false },
    error: { type: String, default: '' },
});

const emit = defineEmits(['update:modelValue', 'confirm']);

const reason = ref('');

const quickReasons = [
    'Customer membatalkan pesanan',
    'Stok barang tidak tersedia',
    'Pembayaran tidak jadi dilakukan',
    'Salah pesan barang',
];

const valid = computed(() => reason.value.trim().length >= 5);

watch(
    () => props.modelValue,
    (open) => {
        if (open) {
            reason.value = '';
        }
    },
);
</script>

<template>
    <AppModal
        :model-value="modelValue"
        title="Batalkan Pesanan"
        width="480px"
        :closable="!loading"
        @update:model-value="emit('update:modelValue', $event)"
    >
        <p style="margin-top: 0">
            Pesanan <strong>{{ order?.order_number }}</strong> akan dibatalkan dan tidak dapat dilanjutkan kembali.
            <span v-if="order?.status === 'MENUNGGU'">Stok barang akan dikembalikan.</span>
        </p>

        <div class="chip-row">
            <button v-for="item in quickReasons" :key="item" class="chip-btn" type="button" @click="reason = item">
                {{ item }}
            </button>
        </div>

        <div class="form-group">
            <label class="form-label" for="cancel-reason">Alasan pembatalan (wajib)</label>
            <textarea
                id="cancel-reason"
                v-model="reason"
                class="input"
                :class="{ 'is-invalid': error }"
                rows="3"
                maxlength="500"
            ></textarea>
            <span v-if="error" class="form-error">{{ error }}</span>
            <span v-else class="hint">Minimal 5 karakter.</span>
        </div>

        <template #footer>
            <button class="btn btn-outline" type="button" :disabled="loading" @click="emit('update:modelValue', false)">
                Kembali
            </button>
            <button class="btn btn-danger" type="button" :disabled="loading || !valid" @click="emit('confirm', reason.trim())">
                {{ loading ? 'Memproses...' : 'Ya, Batalkan Pesanan' }}
            </button>
        </template>
    </AppModal>
</template>
