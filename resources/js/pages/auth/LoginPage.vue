<script setup>
import { computed, reactive, ref } from 'vue';
import { useRoute, useRouter } from 'vue-router';
import AppIcon from '@/components/common/AppIcon.vue';
import { useAuthStore } from '@/stores/auth';
import { ROLE_HOME } from '@/lib/roles';

const auth = useAuthStore();
const route = useRoute();
const router = useRouter();

const form = reactive({ login: '', password: '' });
const showPassword = ref(false);
const loading = ref(false);
const message = ref('');
const fieldErrors = reactive({ login: '', password: '' });

const canSubmit = computed(() => form.login.trim() !== '' && form.password !== '' && !loading.value);

function resolveRedirect(user) {
    const target = route.query.redirect;

    if (typeof target === 'string' && target.startsWith('/') && !target.startsWith('//')) {
        return target;
    }

    return ROLE_HOME[user.role] ?? '/';
}

async function submit() {
    if (!canSubmit.value) {
        return;
    }

    loading.value = true;
    message.value = '';
    fieldErrors.login = '';
    fieldErrors.password = '';

    try {
        const user = await auth.login({ login: form.login.trim(), password: form.password });
        await router.replace(resolveRedirect(user));
    } catch (error) {
        const response = error.response;

        if (response?.status === 422) {
            const errors = response.data.errors ?? {};
            fieldErrors.login = errors.login?.[0] ?? '';
            fieldErrors.password = errors.password?.[0] ?? '';
        } else if (response?.data?.message) {
            message.value = response.data.message;
        } else {
            message.value = 'Tidak dapat terhubung ke server. Periksa koneksi Anda.';
        }

        form.password = '';
    } finally {
        loading.value = false;
    }
}
</script>

<template>
    <main class="login">
        <div class="login__card card">
            <div class="login__brand">
                <span class="login__logo">B7</span>
                <h1>BroSeven! Shop</h1>
                <p>Masuk untuk mengelola toko Anda.</p>
            </div>

            <div v-if="message" class="alert alert-danger" role="alert">{{ message }}</div>

            <form novalidate @submit.prevent="submit">
                <div class="form-group">
                    <label class="form-label" for="login">Username atau Email</label>
                    <input
                        id="login"
                        v-model="form.login"
                        class="input"
                        :class="{ 'is-invalid': fieldErrors.login }"
                        type="text"
                        autocomplete="username"
                        autofocus
                    />
                    <span v-if="fieldErrors.login" class="form-error">{{ fieldErrors.login }}</span>
                </div>

                <div class="form-group">
                    <label class="form-label" for="password">Password</label>
                    <div class="login__password">
                        <input
                            id="password"
                            v-model="form.password"
                            class="input"
                            :class="{ 'is-invalid': fieldErrors.password }"
                            :type="showPassword ? 'text' : 'password'"
                            autocomplete="current-password"
                        />
                        <button
                            class="login__eye"
                            type="button"
                            :aria-label="showPassword ? 'Sembunyikan password' : 'Tampilkan password'"
                            @click="showPassword = !showPassword"
                        >
                            <AppIcon :name="showPassword ? 'eye-off' : 'eye'" :size="18" />
                        </button>
                    </div>
                    <span v-if="fieldErrors.password" class="form-error">{{ fieldErrors.password }}</span>
                </div>

                <button class="btn btn-primary btn-block" type="submit" :disabled="!canSubmit">
                    {{ loading ? 'Memproses...' : 'Masuk' }}
                </button>
            </form>
        </div>
    </main>
</template>

<style scoped>
.login {
    display: grid;
    min-height: 100vh;
    place-items: center;
    padding: 24px 16px;
    background: linear-gradient(160deg, var(--background) 55%, color-mix(in srgb, var(--primary) 18%, var(--background)));
}

.login__card {
    width: 100%;
    max-width: 420px;
    padding: 32px;
}

.login__brand {
    margin-bottom: 24px;
    text-align: center;
}

.login__brand h1 {
    margin-bottom: 4px;
    font-size: 24px;
}

.login__brand p {
    margin: 0;
    color: var(--text-secondary);
}

.login__logo {
    display: inline-grid;
    place-items: center;
    width: 56px;
    height: 56px;
    margin-bottom: 12px;
    border-radius: var(--radius-lg);
    background: var(--primary);
    color: var(--dark);
    font-size: 22px;
    font-weight: 800;
}

.login__password {
    position: relative;
}

.login__password .input {
    padding-right: 44px;
}

.login__eye {
    position: absolute;
    top: 50%;
    right: 8px;
    display: grid;
    place-items: center;
    padding: 6px;
    border: 0;
    border-radius: var(--radius-sm);
    background: transparent;
    color: var(--text-secondary);
    cursor: pointer;
    transform: translateY(-50%);
}
</style>
