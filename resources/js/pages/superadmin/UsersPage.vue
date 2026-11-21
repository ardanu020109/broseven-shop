<script setup>
import { onMounted, reactive, ref, watch } from 'vue';
import http from '@/lib/http';
import AppIcon from '@/components/common/AppIcon.vue';
import AppPagination from '@/components/common/AppPagination.vue';
import ConfirmDialog from '@/components/common/ConfirmDialog.vue';
import StatusBadge from '@/components/common/StatusBadge.vue';
import ResetPasswordModal from '@/components/users/ResetPasswordModal.vue';
import UserDetailModal from '@/components/users/UserDetailModal.vue';
import UserFormModal from '@/components/users/UserFormModal.vue';
import { useToast } from '@/composables/useToast';
import { useAuthStore } from '@/stores/auth';
import { debounce, errorMessage, formatDateTime } from '@/lib/utils';

const toast = useToast();
const auth = useAuthStore();

const items = ref([]);
const loading = ref(false);
const meta = reactive({ page: 1, lastPage: 1, total: 0, from: 0, to: 0 });
const filters = reactive({ search: '', role: '', status: '', perPage: 10 });

const form = reactive({ open: false, user: null });
const detail = reactive({ open: false, id: null });
const reset = reactive({ open: false, user: null });
const confirm = reactive({ open: false, kind: 'toggle', user: null, loading: false });
let requestId = 0;

const roleBadge = { superadmin: 'badge-dark', kasir: 'badge-info', admin_master: 'badge-success' };

async function load() {
    const current = ++requestId;
    loading.value = true;

    try {
        const { data } = await http.get('/users', {
            params: {
                page: meta.page,
                search: filters.search || undefined,
                role: filters.role || undefined,
                status: filters.status || undefined,
                per_page: filters.perPage,
            },
        });

        if (current !== requestId) {
            return;
        }

        if (data.data.length === 0 && data.current_page > 1) {
            meta.page = data.last_page;
            await load();
            return;
        }

        items.value = data.data;
        meta.page = data.current_page;
        meta.lastPage = data.last_page;
        meta.total = data.total;
        meta.from = data.from ?? 0;
        meta.to = data.to ?? 0;
    } catch (error) {
        if (current === requestId) {
            toast.error(errorMessage(error, 'Gagal memuat pengguna.'));
        }
    } finally {
        if (current === requestId) {
            loading.value = false;
        }
    }
}

const onSearch = debounce(() => {
    meta.page = 1;
    load();
});

watch(() => filters.search, onSearch);
watch(
    () => [filters.role, filters.status, filters.perPage],
    () => {
        meta.page = 1;
        load();
    },
);

onMounted(load);

function changePage(page) {
    meta.page = page;
    load();
}

function openCreate() {
    form.user = null;
    form.open = true;
}

function openEdit(user) {
    form.user = user;
    form.open = true;
}

function ask(kind, user) {
    confirm.kind = kind;
    confirm.user = user;
    confirm.open = true;
}

async function runConfirm() {
    confirm.loading = true;

    try {
        const { data } = confirm.kind === 'delete'
            ? await http.delete(`/users/${confirm.user.id}`)
            : await http.post(`/users/${confirm.user.id}/toggle`);

        toast.success(data.message);
        await load();
    } catch (error) {
        toast.error(errorMessage(error));
    } finally {
        confirm.loading = false;
        confirm.open = false;
    }
}
</script>

<template>
    <section>
        <div class="page-header">
            <div>
                <h1>Manajemen User</h1>
                <p>Kelola akun Superadmin, Kasir, dan Admin Master.</p>
            </div>
            <div class="page-actions">
                <button class="btn btn-primary" type="button" @click="openCreate">
                    <AppIcon name="plus" :size="18" /> Tambah Pengguna
                </button>
            </div>
        </div>

        <div class="toolbar">
            <div class="search-box">
                <span class="search-box__icon"><AppIcon name="search" :size="18" /></span>
                <input v-model="filters.search" class="input" type="search" placeholder="Cari nama, username, atau email..." />
            </div>
            <select v-model="filters.role" class="input filter" aria-label="Filter role">
                <option value="">Semua role</option>
                <option value="superadmin">Superadmin</option>
                <option value="kasir">Kasir</option>
                <option value="admin_master">Admin Master</option>
            </select>
            <select v-model="filters.status" class="input filter" aria-label="Filter status">
                <option value="">Semua status</option>
                <option value="active">Aktif</option>
                <option value="inactive">Nonaktif</option>
            </select>
        </div>

        <div class="table-wrap">
            <table class="table">
                <thead>
                    <tr>
                        <th>Pengguna</th>
                        <th>Email</th>
                        <th>Role</th>
                        <th>Status</th>
                        <th>Login Terakhir</th>
                        <th class="num">Aksi</th>
                    </tr>
                </thead>
                <tbody>
                    <tr v-if="loading && items.length === 0">
                        <td colspan="6" class="loading-line">Memuat data...</td>
                    </tr>
                    <tr v-else-if="items.length === 0">
                        <td colspan="6" class="table-empty">Belum ada pengguna yang cocok.</td>
                    </tr>
                    <tr v-for="user in items" :key="user.id">
                        <td>
                            <div class="user-cell">
                                <span class="avatar">
                                    <img v-if="user.avatar_url" :src="user.avatar_url" :alt="user.name" loading="lazy" />
                                    <template v-else>{{ user.name.slice(0, 1).toUpperCase() }}</template>
                                </span>
                                <div>
                                    <strong>{{ user.name }}</strong>
                                    <span v-if="user.is_primary" class="badge badge-dark" style="margin-left: 6px">Utama</span>
                                    <div class="hint">@{{ user.username }}</div>
                                </div>
                            </div>
                        </td>
                        <td>{{ user.email }}</td>
                        <td><span class="badge" :class="roleBadge[user.role]">{{ user.role_label }}</span></td>
                        <td><StatusBadge :value="user.is_active" /></td>
                        <td class="nowrap">{{ formatDateTime(user.last_login_at) }}</td>
                        <td>
                            <div class="table-actions">
                                <button class="btn btn-outline btn-sm" type="button" title="Detail" @click="detail.id = user.id; detail.open = true">
                                    <AppIcon name="eye" :size="16" />
                                </button>
                                <button class="btn btn-outline btn-sm" type="button" title="Edit" @click="openEdit(user)">
                                    <AppIcon name="edit" :size="16" />
                                </button>
                                <button class="btn btn-outline btn-sm" type="button" title="Reset password" @click="reset.user = user; reset.open = true">
                                    <AppIcon name="key" :size="16" />
                                </button>
                                <template v-if="!user.is_primary && user.id !== auth.user?.id">
                                    <button class="btn btn-outline btn-sm" type="button" :title="user.is_active ? 'Nonaktifkan' : 'Aktifkan'" @click="ask('toggle', user)">
                                        <AppIcon name="power" :size="16" />
                                    </button>
                                    <button class="btn btn-outline-danger btn-sm" type="button" title="Hapus" @click="ask('delete', user)">
                                        <AppIcon name="trash" :size="16" />
                                    </button>
                                </template>
                            </div>
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>

        <AppPagination
            :page="meta.page"
            :last-page="meta.lastPage"
            :total="meta.total"
            :from="meta.from"
            :to="meta.to"
            @change="changePage"
        />

        <UserFormModal v-model="form.open" :user="form.user" @saved="load" />
        <UserDetailModal v-model="detail.open" :user-id="detail.id" />
        <ResetPasswordModal v-model="reset.open" :user="reset.user" />

        <ConfirmDialog
            v-model="confirm.open"
            :title="confirm.kind === 'delete' ? 'Hapus Pengguna' : confirm.user?.is_active ? 'Nonaktifkan Pengguna' : 'Aktifkan Pengguna'"
            :message="confirm.kind === 'delete'
                ? `Hapus akun '${confirm.user?.username ?? ''}'? Riwayat transaksinya tetap tersimpan, tetapi akun tidak dapat login lagi.`
                : `${confirm.user?.is_active ? 'Nonaktifkan' : 'Aktifkan'} akun '${confirm.user?.username ?? ''}'?`"
            :confirm-text="confirm.kind === 'delete' ? 'Ya, Hapus' : 'Ya, Lanjutkan'"
            :danger="confirm.kind === 'delete' || Boolean(confirm.user?.is_active)"
            :loading="confirm.loading"
            @confirm="runConfirm"
        />
    </section>
</template>
