<script setup>
import { computed, onBeforeUnmount, onMounted, reactive, ref } from 'vue';
import http from '@/lib/http';
import AppIcon from '@/components/common/AppIcon.vue';
import { useToast } from '@/composables/useToast';
import { useAuthStore } from '@/stores/auth';
import { errorMessage, validationErrors } from '@/lib/utils';

const ALLOWED = ['image/jpeg', 'image/png', 'image/webp'];
const MAX_BYTES = 2 * 1024 * 1024;

const auth = useAuthStore();
const toast = useToast();

const loading = ref(true);
const profile = reactive({ name: '', username: '', email: '', role_label: '', avatar_url: null });
const form = reactive({ name: '', username: '', email: '' });
const errors = ref({});
const saving = ref(false);

const fileInput = ref(null);
const avatarFile = ref(null);
const avatarPreview = ref(null);
const removeAvatar = ref(false);

const password = reactive({ current_password: '', password: '', password_confirmation: '' });
const passwordErrors = ref({});
const changing = ref(false);
const showPassword = ref(false);

const shownAvatar = computed(() => avatarPreview.value || (removeAvatar.value ? null : profile.avatar_url));
const initial = computed(() => (form.name || '?').slice(0, 1).toUpperCase());

function clearAvatarState() {
    if (avatarPreview.value) {
        URL.revokeObjectURL(avatarPreview.value);
    }

    avatarPreview.value = null;
    avatarFile.value = null;
    removeAvatar.value = false;
}

function fill(user) {
    Object.assign(profile, user);
    form.name = user.name;
    form.username = user.username;
    form.email = user.email;
    clearAvatarState();
}

onMounted(async () => {
    try {
        const { data } = await http.get('/profile');
        fill(data.user);
    } catch (error) {
        toast.error(errorMessage(error, 'Gagal memuat profil.'));
    } finally {
        loading.value = false;
    }
});

onBeforeUnmount(clearAvatarState);

function pickFile(event) {
    const file = event.target.files?.[0];
    event.target.value = '';

    if (!file) {
        return;
    }

    if (!ALLOWED.includes(file.type)) {
        errors.value = { ...errors.value, avatar: 'Foto harus berformat JPG, PNG, atau WEBP.' };
        return;
    }

    if (file.size > MAX_BYTES) {
        errors.value = { ...errors.value, avatar: 'Ukuran foto maksimal 2 MB.' };
        return;
    }

    const { avatar, ...rest } = errors.value;
    errors.value = rest;

    if (avatarPreview.value) {
        URL.revokeObjectURL(avatarPreview.value);
    }

    avatarFile.value = file;
    avatarPreview.value = URL.createObjectURL(file);
    removeAvatar.value = false;
}

function dropAvatar() {
    if (avatarPreview.value) {
        URL.revokeObjectURL(avatarPreview.value);
    }

    avatarPreview.value = null;
    avatarFile.value = null;
    removeAvatar.value = Boolean(profile.avatar_url);
}

async function saveProfile() {
    saving.value = true;
    errors.value = {};

    const body = new FormData();
    body.append('name', form.name.trim());
    body.append('username', form.username.trim());
    body.append('email', form.email.trim());

    if (avatarFile.value) {
        body.append('avatar', avatarFile.value);
    } else if (removeAvatar.value) {
        body.append('remove_avatar', '1');
    }

    try {
        const { data } = await http.post('/profile', body);

        auth.user = data.user;
        fill(data.user);
        toast.success(data.message);
    } catch (error) {
        errors.value = validationErrors(error);
        toast.error(errorMessage(error));
    } finally {
        saving.value = false;
    }
}

async function changePassword() {
    changing.value = true;
    passwordErrors.value = {};

    try {
        const { data } = await http.put('/profile/password', { ...password });

        password.current_password = '';
        password.password = '';
        password.password_confirmation = '';
        toast.success(data.message);
    } catch (error) {
        passwordErrors.value = validationErrors(error);
        toast.error(errorMessage(error));
    } finally {
        changing.value = false;
    }
}
</script>

<template>
    <section>
        <div class="page-header">
            <div>
                <h1>Profil Saya</h1>
                <p>Kelola data akun dan password Anda.</p>
            </div>
        </div>

        <div v-if="loading" class="card loading-line">Memuat profil...</div>

        <div v-else class="two-col">
            <form class="card" novalidate @submit.prevent="saveProfile">
                <h3>Data Akun</h3>

                <div class="user-cell" style="margin-bottom: 18px">
                    <span class="avatar avatar--lg">
                        <img v-if="shownAvatar" :src="shownAvatar" alt="Foto profil" />
                        <template v-else>{{ initial }}</template>
                    </span>
                    <div>
                        <div class="uploader__actions">
                            <button class="btn btn-outline btn-sm" type="button" @click="fileInput.click()">
                                <AppIcon name="upload" :size="16" /> Pilih Foto
                            </button>
                            <button v-if="shownAvatar" class="btn btn-outline-danger btn-sm" type="button" @click="dropAvatar">
                                <AppIcon name="trash" :size="16" /> Hapus Foto
                            </button>
                        </div>
                        <input ref="fileInput" type="file" accept="image/jpeg,image/png,image/webp" hidden @change="pickFile" />
                        <span class="hint">JPG, PNG, atau WEBP. Maksimal 2 MB.</span>
                        <div v-if="errors.avatar" class="form-error">{{ errors.avatar }}</div>
                    </div>
                </div>

                <div class="form-group">
                    <label class="form-label" for="p-name">Nama Lengkap</label>
                    <input id="p-name" v-model="form.name" class="input" :class="{ 'is-invalid': errors.name }" type="text" maxlength="255" />
                    <span v-if="errors.name" class="form-error">{{ errors.name }}</span>
                </div>

                <div class="form-row form-row--2">
                    <div class="form-group">
                        <label class="form-label" for="p-username">Username</label>
                        <input id="p-username" v-model="form.username" class="input" :class="{ 'is-invalid': errors.username }" type="text" maxlength="50" autocomplete="username" />
                        <span v-if="errors.username" class="form-error">{{ errors.username }}</span>
                    </div>
                    <div class="form-group">
                        <label class="form-label" for="p-email">Email</label>
                        <input id="p-email" v-model="form.email" class="input" :class="{ 'is-invalid': errors.email }" type="email" maxlength="255" autocomplete="email" />
                        <span v-if="errors.email" class="form-error">{{ errors.email }}</span>
                    </div>
                </div>

                <div class="form-group">
                    <label class="form-label" for="p-role">Role</label>
                    <input id="p-role" class="input" type="text" :value="profile.role_label" disabled />
                    <span class="hint">Role dan hak akses hanya dapat diubah oleh Superadmin.</span>
                </div>

                <button class="btn btn-primary" type="submit" :disabled="saving">{{ saving ? 'Menyimpan...' : 'Simpan Profil' }}</button>
            </form>

            <form class="card" novalidate @submit.prevent="changePassword">
                <h3>Ganti Password</h3>

                <div class="form-group">
                    <label class="form-label" for="pw-current">Password saat ini</label>
                    <input id="pw-current" v-model="password.current_password" class="input" :class="{ 'is-invalid': passwordErrors.current_password }" :type="showPassword ? 'text' : 'password'" autocomplete="current-password" />
                    <span v-if="passwordErrors.current_password" class="form-error">{{ passwordErrors.current_password }}</span>
                </div>

                <div class="form-group">
                    <label class="form-label" for="pw-new">Password baru</label>
                    <input id="pw-new" v-model="password.password" class="input" :class="{ 'is-invalid': passwordErrors.password }" :type="showPassword ? 'text' : 'password'" autocomplete="new-password" />
                    <span v-if="passwordErrors.password" class="form-error">{{ passwordErrors.password }}</span>
                    <span v-else class="hint">Minimal 8 karakter, mengandung huruf dan angka.</span>
                </div>

                <div class="form-group">
                    <label class="form-label" for="pw-confirm">Ulangi password baru</label>
                    <input id="pw-confirm" v-model="password.password_confirmation" class="input" :type="showPassword ? 'text' : 'password'" autocomplete="new-password" />
                </div>

                <label class="check" style="margin-bottom: 16px">
                    <input v-model="showPassword" type="checkbox" /> Tampilkan password
                </label>

                <div>
                    <button
                        class="btn btn-primary"
                        type="submit"
                        :disabled="changing || !password.current_password || !password.password"
                    >
                        {{ changing ? 'Menyimpan...' : 'Ganti Password' }}
                    </button>
                </div>
            </form>
        </div>
    </section>
</template>
