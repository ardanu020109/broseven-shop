<script setup>
import { computed, reactive, ref, watch } from 'vue';
import http from '@/lib/http';
import AppIcon from '@/components/common/AppIcon.vue';
import AppModal from '@/components/common/AppModal.vue';
import { useToast } from '@/composables/useToast';
import { generatePassword } from '@/lib/password';
import { errorMessage, validationErrors } from '@/lib/utils';

const props = defineProps({
    modelValue: { type: Boolean, default: false },
    user: { type: Object, default: null },
});

const emit = defineEmits(['update:modelValue', 'saved']);

const toast = useToast();
const form = reactive({ name: '', username: '', email: '', role: 'kasir', is_active: true, password: '' });
const errors = ref({});
const saving = ref(false);
const showPassword = ref(false);

const isEdit = computed(() => props.user !== null);
const locked = computed(() => Boolean(props.user?.is_primary));

watch(
    () => props.modelValue,
    (open) => {
        if (!open) {
            return;
        }

        form.name = props.user?.name ?? '';
        form.username = props.user?.username ?? '';
        form.email = props.user?.email ?? '';
        form.role = props.user?.role ?? 'kasir';
        form.is_active = props.user?.is_active ?? true;
        form.password = '';
        errors.value = {};
        showPassword.value = false;
    },
    { immediate: true },
);

function fillPassword() {
    form.password = generatePassword();
    showPassword.value = true;
}

async function save() {
    saving.value = true;
    errors.value = {};

    const payload = {
        name: form.name.trim(),
        username: form.username.trim(),
        email: form.email.trim(),
        role: form.role,
        is_active: form.is_active,
    };

    if (!isEdit.value) {
        payload.password = form.password;
    }

    try {
        const { data } = isEdit.value
            ? await http.put(`/users/${props.user.id}`, payload)
            : await http.post('/users', payload);

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
        :title="isEdit ? 'Edit Pengguna' : 'Tambah Pengguna'"
        :closable="!saving"
        @update:model-value="emit('update:modelValue', $event)"
    >
        <form id="user-form" novalidate @submit.prevent="save">
            <div class="form-group">
                <label class="form-label" for="u-name">Nama Lengkap</label>
                <input id="u-name" v-model="form.name" class="input" :class="{ 'is-invalid': errors.name }" type="text" maxlength="255" />
                <span v-if="errors.name" class="form-error">{{ errors.name }}</span>
            </div>

            <div class="form-row form-row--2">
                <div class="form-group">
                    <label class="form-label" for="u-username">Username</label>
                    <input id="u-username" v-model="form.username" class="input" :class="{ 'is-invalid': errors.username }" type="text" maxlength="50" autocomplete="off" />
                    <span v-if="errors.username" class="form-error">{{ errors.username }}</span>
                </div>
                <div class="form-group">
                    <label class="form-label" for="u-email">Email</label>
                    <input id="u-email" v-model="form.email" class="input" :class="{ 'is-invalid': errors.email }" type="email" maxlength="255" autocomplete="off" />
                    <span v-if="errors.email" class="form-error">{{ errors.email }}</span>
                </div>
            </div>

            <div class="form-group">
                <label class="form-label" for="u-role">Role</label>
                <select id="u-role" v-model="form.role" class="input" :class="{ 'is-invalid': errors.role }" :disabled="locked">
                    <option value="superadmin">Superadmin</option>
                    <option value="kasir">Kasir</option>
                    <option value="admin_master">Admin Master</option>
                </select>
                <span v-if="errors.role" class="form-error">{{ errors.role }}</span>
                <span v-else-if="locked" class="hint">Role akun Superadmin utama tidak dapat diubah.</span>
            </div>

            <div v-if="!isEdit" class="form-group">
                <label class="form-label" for="u-password">Password</label>
                <div style="display: flex; gap: 8px">
                    <input
                        id="u-password"
                        v-model="form.password"
                        class="input"
                        :class="{ 'is-invalid': errors.password }"
                        :type="showPassword ? 'text' : 'password'"
                        autocomplete="new-password"
                    />
                    <button class="btn btn-outline btn-icon" type="button" :aria-label="showPassword ? 'Sembunyikan' : 'Tampilkan'" @click="showPassword = !showPassword">
                        <AppIcon :name="showPassword ? 'eye-off' : 'eye'" :size="18" />
                    </button>
                    <button class="btn btn-outline btn-sm" type="button" @click="fillPassword">Buat Acak</button>
                </div>
                <span v-if="errors.password" class="form-error">{{ errors.password }}</span>
                <span v-else class="hint">Minimal 8 karakter, mengandung huruf dan angka.</span>
            </div>

            <label class="check">
                <input v-model="form.is_active" type="checkbox" :disabled="locked" />
                Akun aktif
            </label>
            <p v-if="errors.is_active" class="form-error" style="margin: 6px 0 0">{{ errors.is_active }}</p>
        </form>

        <template #footer>
            <button class="btn btn-outline" type="button" :disabled="saving" @click="emit('update:modelValue', false)">Batal</button>
            <button class="btn btn-primary" type="submit" form="user-form" :disabled="saving">
                {{ saving ? 'Menyimpan...' : 'Simpan' }}
            </button>
        </template>
    </AppModal>
</template>
