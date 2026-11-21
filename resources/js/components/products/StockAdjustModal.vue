<script setup>
import { computed, reactive, ref, watch } from 'vue';
import http from '@/lib/http';
import AppModal from '@/components/common/AppModal.vue';
import { useToast } from '@/composables/useToast';
import { errorMessage, formatNumber, validationErrors } from '@/lib/utils';

const props = defineProps({
    modelValue: { type: Boolean, default: false },
    product: { type: Object, required: true },
});

const emit = defineEmits(['update:modelValue', 'saved']);

const toast = useToast();
const form = reactive({ type: 'STOCK_IN', quantity: null, note: '' });
const errors = ref({});
const saving = ref(false);

watch(
    () => props.modelValue,
    (open) => {
        if (open) {
            form.type = 'STOCK_IN';
            form.quantity = null;
            form.note = '';
            errors.value = {};
        }
    },
);

const resultStock = computed(() => props.product.stock + Number(form.quantity || 0));
const hasQuantity = computed(() => form.quantity !== null && form.quantity !== '' && Number(form.quantity) !== 0);

async function submit() {
    saving.value = true;
    errors.value = {};

    try {
        const { data } = await http.post(`/products/${props.product.id}/stock-adjustments`, {
            type: form.type,
            quantity: form.quantity === null || form.quantity === '' ? null : Number(form.quantity),
            note: form.note.trim(),
        });

        toast.success(data.message);
        emit('saved', data.data);
        emit('update:modelValue', false);
    } catch (error) {
        errors.value = validationErrors(error);
        toast.error(errorMessage(error));
    } finally {
        saving.value = false;
    }
}
</script>

<template>
    <AppModal
        :model-value="modelValue"
        title="Sesuaikan Stok"
        :closable="!saving"
        @update:model-value="emit('update:modelValue', $event)"
    >
        <p style="margin-top: 0">
            <strong>{{ product.name }}</strong><br />
            <span class="muted">Stok saat ini: {{ formatNumber(product.stock) }} {{ product.unit?.name ?? '' }}</span>
        </p>

        <div class="form-group">
            <span class="form-label">Jenis Perubahan</span>
            <div class="segmented">
                <button type="button" :class="{ 'is-active': form.type === 'STOCK_IN' }" @click="form.type = 'STOCK_IN'">
                    Stok Masuk
                </button>
                <button type="button" :class="{ 'is-active': form.type === 'ADJUSTMENT' }" @click="form.type = 'ADJUSTMENT'">
                    Penyesuaian
                </button>
            </div>
            <span v-if="errors.type" class="form-error">{{ errors.type }}</span>
        </div>

        <div class="form-group">
            <label class="form-label" for="adjust-qty">
                {{ form.type === 'STOCK_IN' ? 'Jumlah Masuk' : 'Perubahan (+ menambah, - mengurangi)' }}
            </label>
            <input
                id="adjust-qty"
                v-model.number="form.quantity"
                class="input"
                :class="{ 'is-invalid': errors.quantity }"
                type="number"
                step="1"
                :min="form.type === 'STOCK_IN' ? 1 : undefined"
            />
            <span v-if="errors.quantity" class="form-error">{{ errors.quantity }}</span>
        </div>

        <div class="form-group">
            <label class="form-label" for="adjust-note">
                Keterangan {{ form.type === 'ADJUSTMENT' ? '(wajib)' : '(opsional)' }}
            </label>
            <input
                id="adjust-note"
                v-model="form.note"
                class="input"
                :class="{ 'is-invalid': errors.note }"
                type="text"
                maxlength="255"
                :placeholder="form.type === 'ADJUSTMENT' ? 'Contoh: koreksi hasil stok opname' : 'Contoh: pembelian dari supplier'"
            />
            <span v-if="errors.note" class="form-error">{{ errors.note }}</span>
        </div>

        <div v-if="hasQuantity" class="result-box" :class="{ 'result-box--warn': resultStock < 0 }">
            Stok setelah perubahan: <strong>{{ formatNumber(resultStock) }}</strong>
            <span v-if="resultStock < 0"> (tidak boleh negatif)</span>
        </div>

        <template #footer>
            <button class="btn btn-outline" type="button" :disabled="saving" @click="emit('update:modelValue', false)">
                Batal
            </button>
            <button class="btn btn-primary" type="button" :disabled="saving || !hasQuantity || resultStock < 0" @click="submit">
                {{ saving ? 'Menyimpan...' : 'Simpan' }}
            </button>
        </template>
    </AppModal>
</template>
