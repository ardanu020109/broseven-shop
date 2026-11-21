<script setup>
import { ref, watch } from 'vue';
import http from '@/lib/http';
import AppIcon from '@/components/common/AppIcon.vue';
import AppModal from '@/components/common/AppModal.vue';
import { useToast } from '@/composables/useToast';
import { errorMessage, validationErrors } from '@/lib/utils';

const props = defineProps({
    modelValue: { type: Boolean, default: false },
    user: { type: Object, default: null },
});

const emit = defineEmits(['update:modelValue']);

const toast = useToast();
const mode = ref('auto');
const password = ref('');
const errors = ref({});
const saving = ref(false);
const result = ref(null);

watch(
    () => props.modelValue,
    (open) => {
        if (open) {
            mode.value = 'auto';
            password.value = '';
            errors.value = {};
            result.value = null;
        }
    },
    { immediate: true },
);

async function submit() {
    saving.value = true;
    errors.value = {};

    try {
        const { data } = await http.post(`/users/${props.user.id}/reset-password`, mode.value === 'manual' ? { password: password.value } : {});
        result.value = data;
        toast.success(data.message);
    } catch (error) {
        errors.value = validationErrors(error);
        toast.error(errorMessage(error));
    } finally {
        saving.value = false;
    }
}

async function copy() {
    try {
        await navigator.clipboard.writeText(result.value.password);
        toast.success('Password disalin.');
    } catch {
        toast.error('Gagal menyalin. Salin manual dari kotak di atas.');
    }
}

function close() {
    result.value = null;
    emit('update:modelValue', false);
}
</script>

<template>
    <AppModal
        :model-value="modelValue"
        title="Reset Password"
        width="480px"
        :closable="!saving"
        @update:model-value="close"
    >
        <p style="margin-top: 0">Pengguna: <strong>{{ user?.name }}</strong> (@{{ user?.username }})</p>

        <div v-if="result" class="pw-result">
            <strong>Password baru</strong>
            <code>{{ result.password }}</code>
            <p class="hint" style="margin: 0 0 10px">Password hanya ditampilkan sekali. Sampaikan kepada pengguna dan minta ia menggantinya lewat halaman profil.</p>
            <button class="btn btn-outline btn-sm" type="button" @click="copy"><AppIcon name="copy" :size="16" /> Salin</button>
        </div>

        <template v-else>
            <div class="segmented" style="margin-bottom: 14px">
                <button type="button" :class="{ 'is-active': mode === 'auto' }" @click="mode = 'auto'">Buat Otomatis</button>
                <button type="button" :class="{ 'is-active': mode === 'manual' }" @click="mode = 'manual'">Isi Manual</button>
            </div>

            <div v-if="mode === 'manual'" class="form-group">
                <label class="form-label" for="r-password">Password baru</label>
                <input id="r-password" v-model="password" class="input" :class="{ 'is-invalid': errors.password }" type="text" autocomplete="off" />
                <span v-if="errors.password" class="form-error">{{ errors.password }}</span>
                <span v-else class="hint">Minimal 8 karakter, mengandung huruf dan angka.</span>
            </div>
            <p v-else class="hint" style="margin: 0">Sistem akan membuat password acak yang kuat.</p>
        </template>

        <template #footer>
            <button class="btn btn-outline" type="button" :disabled="saving" @click="close">{{ result ? 'Tutup' : 'Batal' }}</button>
            <button v-if="!result" class="btn btn-primary" type="button" :disabled="saving || (mode === 'manual' && password === '')" @click="submit">
                {{ saving ? 'Memproses...' : 'Reset Password' }}
            </button>
        </template>
    </AppModal>
</template>
