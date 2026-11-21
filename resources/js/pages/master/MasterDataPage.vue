<script setup>
import { computed, onMounted, reactive, ref, watch } from 'vue';
import http from '@/lib/http';
import AppIcon from '@/components/common/AppIcon.vue';
import AppModal from '@/components/common/AppModal.vue';
import AppPagination from '@/components/common/AppPagination.vue';
import ConfirmDialog from '@/components/common/ConfirmDialog.vue';
import StatusBadge from '@/components/common/StatusBadge.vue';
import { useToast } from '@/composables/useToast';
import { MASTER_CONFIG } from '@/lib/masterConfig';
import { debounce, errorMessage, formatNumber, validationErrors } from '@/lib/utils';

const props = defineProps({
    entity: { type: String, required: true },
});

const toast = useToast();
const config = computed(() => MASTER_CONFIG[props.entity]);

const items = ref([]);
const loading = ref(false);
const meta = reactive({ page: 1, lastPage: 1, total: 0, from: 0, to: 0 });
const filters = reactive({ search: '', status: '', perPage: 10 });
let requestId = 0;

const modal = reactive({ open: false, id: null, saving: false });
const form = reactive({ name: '', extra: '', is_active: true });
const errors = ref({});

const confirm = reactive({ open: false, item: null, loading: false });

async function load() {
    const current = ++requestId;
    loading.value = true;

    try {
        const { data } = await http.get(config.value.endpoint, {
            params: {
                page: meta.page,
                search: filters.search || undefined,
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
            toast.error(errorMessage(error, 'Gagal memuat data.'));
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
    () => [filters.status, filters.perPage],
    () => {
        meta.page = 1;
        load();
    },
);
watch(
    () => props.entity,
    () => {
        filters.search = '';
        filters.status = '';
        meta.page = 1;
        items.value = [];
        load();
    },
);

onMounted(load);

function changePage(page) {
    meta.page = page;
    load();
}

function openCreate() {
    modal.id = null;
    form.name = '';
    form.extra = '';
    form.is_active = true;
    errors.value = {};
    modal.open = true;
}

function openEdit(item) {
    modal.id = item.id;
    form.name = item.name;
    form.extra = item[config.value.extra.key] ?? '';
    form.is_active = item.is_active;
    errors.value = {};
    modal.open = true;
}

async function save() {
    modal.saving = true;
    errors.value = {};

    const payload = {
        name: form.name.trim(),
        is_active: form.is_active,
        [config.value.extra.key]: form.extra.trim(),
    };

    try {
        const response = modal.id
            ? await http.put(`${config.value.endpoint}/${modal.id}`, payload)
            : await http.post(config.value.endpoint, payload);

        toast.success(response.data.message);
        modal.open = false;
        await load();
    } catch (error) {
        errors.value = validationErrors(error);
        toast.error(errorMessage(error));
    } finally {
        modal.saving = false;
    }
}

function askDelete(item) {
    confirm.item = item;
    confirm.open = true;
}

async function remove() {
    confirm.loading = true;

    try {
        const { data } = await http.delete(`${config.value.endpoint}/${confirm.item.id}`);
        toast.success(data.message);
        confirm.open = false;
        await load();
    } catch (error) {
        toast.error(errorMessage(error));
        confirm.open = false;
    } finally {
        confirm.loading = false;
    }
}
</script>

<template>
    <section>
        <div class="page-header">
            <div>
                <h1>{{ config.title }}</h1>
                <p>Kelola data {{ config.singular.toLowerCase() }} yang dipakai pada master barang.</p>
            </div>
            <div class="page-actions">
                <button class="btn btn-primary" type="button" @click="openCreate">
                    <AppIcon name="plus" :size="18" />
                    Tambah {{ config.singular }}
                </button>
            </div>
        </div>

        <div class="toolbar">
            <div class="search-box">
                <span class="search-box__icon"><AppIcon name="search" :size="18" /></span>
                <input v-model="filters.search" class="input" type="search" :placeholder="`Cari ${config.singular.toLowerCase()}...`" />
            </div>
            <select v-model="filters.status" class="input filter" aria-label="Filter status">
                <option value="">Semua status</option>
                <option value="active">Aktif</option>
                <option value="inactive">Nonaktif</option>
            </select>
            <select v-model.number="filters.perPage" class="input filter" aria-label="Jumlah per halaman">
                <option :value="5">5 per halaman</option>
                <option :value="10">10 per halaman</option>
                <option :value="25">25 per halaman</option>
                <option :value="50">50 per halaman</option>
            </select>
        </div>

        <div class="table-wrap">
            <table class="table">
                <thead>
                    <tr>
                        <th>No</th>
                        <th>Nama</th>
                        <th>{{ config.extra.label }}</th>
                        <th class="num">Jumlah Produk</th>
                        <th>Status</th>
                        <th class="num">Aksi</th>
                    </tr>
                </thead>
                <tbody>
                    <tr v-if="loading && items.length === 0">
                        <td colspan="6" class="loading-line">Memuat data...</td>
                    </tr>
                    <tr v-else-if="items.length === 0">
                        <td colspan="6" class="table-empty">Belum ada data yang cocok.</td>
                    </tr>
                    <tr v-for="(item, index) in items" :key="item.id">
                        <td class="muted">{{ meta.from + index }}</td>
                        <td><strong>{{ item.name }}</strong></td>
                        <td class="muted">{{ item[config.extra.key] || '-' }}</td>
                        <td class="num">{{ formatNumber(item.products_count) }}</td>
                        <td><StatusBadge :value="item.is_active" /></td>
                        <td>
                            <div class="table-actions">
                                <button class="btn btn-outline btn-sm" type="button" @click="openEdit(item)">
                                    <AppIcon name="edit" :size="16" /> Edit
                                </button>
                                <button class="btn btn-outline-danger btn-sm" type="button" @click="askDelete(item)">
                                    <AppIcon name="trash" :size="16" /> Hapus
                                </button>
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

        <AppModal
            v-model="modal.open"
            :title="modal.id ? `Edit ${config.singular}` : `Tambah ${config.singular}`"
            :closable="!modal.saving"
        >
            <form id="master-form" novalidate @submit.prevent="save">
                <div class="form-group">
                    <label class="form-label" for="m-name">Nama</label>
                    <input
                        id="m-name"
                        v-model="form.name"
                        class="input"
                        :class="{ 'is-invalid': errors.name }"
                        type="text"
                        :maxlength="config.nameMax"
                        autofocus
                    />
                    <span v-if="errors.name" class="form-error">{{ errors.name }}</span>
                </div>

                <div class="form-group">
                    <label class="form-label" for="m-extra">{{ config.extra.label }} (opsional)</label>
                    <textarea
                        v-if="config.extra.type === 'textarea'"
                        id="m-extra"
                        v-model="form.extra"
                        class="input"
                        :class="{ 'is-invalid': errors[config.extra.key] }"
                        rows="3"
                        :maxlength="config.extra.maxlength"
                    ></textarea>
                    <input
                        v-else
                        id="m-extra"
                        v-model="form.extra"
                        class="input"
                        :class="{ 'is-invalid': errors[config.extra.key] }"
                        type="text"
                        :maxlength="config.extra.maxlength"
                    />
                    <span v-if="errors[config.extra.key]" class="form-error">{{ errors[config.extra.key] }}</span>
                </div>

                <label class="check">
                    <input v-model="form.is_active" type="checkbox" />
                    Aktif
                </label>
            </form>

            <template #footer>
                <button class="btn btn-outline" type="button" :disabled="modal.saving" @click="modal.open = false">Batal</button>
                <button class="btn btn-primary" type="submit" form="master-form" :disabled="modal.saving || form.name.trim() === ''">
                    {{ modal.saving ? 'Menyimpan...' : 'Simpan' }}
                </button>
            </template>
        </AppModal>

        <ConfirmDialog
            v-model="confirm.open"
            :title="`Hapus ${config.singular}`"
            :message="`Hapus &quot;${confirm.item?.name ?? ''}&quot;? Tindakan ini tidak dapat dibatalkan.`"
            confirm-text="Ya, Hapus"
            danger
            :loading="confirm.loading"
            @confirm="remove"
        />
    </section>
</template>
