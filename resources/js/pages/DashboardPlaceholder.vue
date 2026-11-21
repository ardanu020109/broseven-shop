<script setup>
import { ref } from 'vue';
import http from '@/lib/http';
import { useAuthStore } from '@/stores/auth';

const auth = useAuthStore();
const results = ref([]);

const checks = [
    { label: 'Area Superadmin', url: '/superadmin/access-check' },
    { label: 'Area Kasir', url: '/kasir/access-check' },
    { label: 'Area Admin Master', url: '/admin-master/access-check' },
];

async function run(check) {
    let entry;

    try {
        const { status, data } = await http.get(check.url);
        entry = { label: check.label, status, ok: true, message: data.message };
    } catch (error) {
        entry = {
            label: check.label,
            status: error.response?.status ?? 0,
            ok: false,
            message: error.response?.data?.message ?? 'Tidak ada respons dari server.',
        };
    }

    results.value = [entry, ...results.value].slice(0, 6);
}
</script>

<template>
    <section>
        <h1>Halo, {{ auth.user?.name }}</h1>
        <p>Anda masuk sebagai <span class="badge badge-info">{{ auth.user?.role_label }}</span>. Dashboard lengkap dibangun di phase berikutnya.</p>

        <div class="card check">
            <h3>Uji Hak Akses Backend</h3>
            <p>Klik tiap tombol. Hanya area milik role Anda yang boleh menjawab 200, sisanya harus 403.</p>

            <div class="check__actions">
                <button v-for="item in checks" :key="item.url" class="btn btn-outline" type="button" @click="run(item)">
                    {{ item.label }}
                </button>
            </div>

            <ul v-if="results.length" class="check__list">
                <li v-for="(item, index) in results" :key="index" :class="item.ok ? 'is-ok' : 'is-fail'">
                    <span class="badge" :class="item.ok ? 'badge-success' : 'badge-danger'">{{ item.status }}</span>
                    <strong>{{ item.label }}</strong>
                    <span>{{ item.message }}</span>
                </li>
            </ul>
        </div>
    </section>
</template>

<style scoped>
.check {
    max-width: 720px;
    margin-top: 20px;
}

.check__actions {
    display: flex;
    flex-wrap: wrap;
    gap: 10px;
    margin-bottom: 16px;
}

.check__list {
    display: flex;
    flex-direction: column;
    gap: 8px;
    margin: 0;
    padding: 0;
    list-style: none;
}

.check__list li {
    display: flex;
    flex-wrap: wrap;
    align-items: center;
    gap: 10px;
    padding: 10px 12px;
    border: 1px solid var(--border-color);
    border-radius: var(--radius-md);
    font-size: 14px;
}
</style>
