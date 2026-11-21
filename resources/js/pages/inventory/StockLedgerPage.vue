<script setup>
import { computed, onMounted, reactive, ref, watch } from 'vue';
import http from '@/lib/http';
import AppIcon from '@/components/common/AppIcon.vue';
import AppPagination from '@/components/common/AppPagination.vue';
import { useToast } from '@/composables/useToast';
import { useAuthStore } from '@/stores/auth';
import { debounce, errorMessage, formatDateTime, formatNumber } from '@/lib/utils';

const auth = useAuthStore();
const toast = useToast();

const base = computed(() => (auth.role === 'superadmin' ? '/superadmin' : '/admin-master'));

const types = [
    { value: 'STOCK_IN', label: 'Stok Masuk' },
    { value: 'SALE', label: 'Penjualan' },
    { value: 'SALE_CANCELLED', label: 'Pembatalan Penjualan' },
    { value: 'RETURN', label: 'Retur' },
    { value: 'ADJUSTMENT', label: 'Penyesuaian' },
];

const typeBadge = {
    STOCK_IN: 'badge-success',
    RETURN: 'badge-success',
    SALE_CANCELLED: 'badge-success',
    SALE: 'badge-info',
    ADJUSTMENT: 'badge-muted',
};

const summary = ref(null);
const items = ref([]);
const loading = ref(false);
const meta = reactive({ page: 1, lastPage: 1, total: 0, from: 0, to: 0 });
const filters = reactive({ search: '', type: '', date_from: '', date_to: '', perPage: 15 });
let requestId = 0;

function labelOf(type) {
    return types.find((item) => item.value === type)?.label ?? type;
}

async function loadSummary() {
    try {
        const { data } = await http.get('/inventory/summary');
        summary.value = data;
    } catch {
        summary.value = null;
    }
}

async function load() {
    const current = ++requestId;
    loading.value = true;

    try {
        const { data } = await http.get('/inventory/movements', {
            params: {
                page: meta.page,
                search: filters.search || undefined,
                type: filters.type || undefined,
                date_from: filters.date_from || undefined,
                date_to: filters.date_to || undefined,
                per_page: filters.perPage,
            },
        });

        if (current !== requestId) {
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
            toast.error(error.response?.status === 422 ? 'Filter tidak valid.' : errorMessage(error, 'Gagal memuat riwayat stok.'));
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
    () => [filters.type, filters.date_from, filters.date_to, filters.perPage],
    () => {
        meta.page = 1;
        load();
    },
);

onMounted(() => {
    loadSummary();
    load();
});

function changePage(page) {
    meta.page = page;
    load();
}

function reset() {
    filters.search = '';
    filters.type = '';
    filters.date_from = '';
    filters.date_to = '';
}
</script>

<template>
    <section>
        <div class="page-header">
            <div>
                <h1>Riwayat Stok</h1>
                <p>Seluruh pergerakan stok. Riwayat bersifat tetap dan tidak dapat diubah.</p>
            </div>
            <div class="page-actions">
                <button class="btn btn-outline" type="button" @click="() => { loadSummary(); load(); }">
                    <AppIcon name="refresh" :size="18" /> Muat Ulang
                </button>
            </div>
        </div>

        <div v-if="summary" class="stat-grid">
            <div class="stat-card stat-card--accent">
                <div class="stat-card__label">Produk Aktif</div>
                <div class="stat-card__value">{{ formatNumber(summary.products_active) }}</div>
            </div>
            <div class="stat-card stat-card--accent">
                <div class="stat-card__label">Total Unit di Stok</div>
                <div class="stat-card__value">{{ formatNumber(summary.units_in_stock) }}</div>
            </div>
            <div class="stat-card stat-card--warn">
                <div class="stat-card__label">Stok Menipis</div>
                <div class="stat-card__value">{{ formatNumber(summary.low_stock) }}</div>
            </div>
            <div class="stat-card stat-card--danger">
                <div class="stat-card__label">Stok Habis</div>
                <div class="stat-card__value">{{ formatNumber(summary.out_of_stock) }}</div>
            </div>
        </div>

        <div v-if="summary" class="type-chips">
            <span class="muted" style="align-self: center">Pergerakan hari ini:</span>
            <span v-for="type in types" :key="type.value" class="badge" :class="typeBadge[type.value]">
                {{ type.label }}: {{ formatNumber(summary.movements_today[type.value] ?? 0) }}
            </span>
        </div>

        <div class="toolbar">
            <div class="search-box">
                <span class="search-box__icon"><AppIcon name="search" :size="18" /></span>
                <input v-model="filters.search" class="input" type="search" placeholder="Cari nama atau kode produk..." />
            </div>
            <select v-model="filters.type" class="input filter" aria-label="Filter jenis">
                <option value="">Semua jenis</option>
                <option v-for="type in types" :key="type.value" :value="type.value">{{ type.label }}</option>
            </select>
            <input v-model="filters.date_from" class="input filter" type="date" aria-label="Dari tanggal" />
            <input v-model="filters.date_to" class="input filter" type="date" aria-label="Sampai tanggal" />
            <select v-model.number="filters.perPage" class="input filter" aria-label="Jumlah per halaman">
                <option :value="15">15 per halaman</option>
                <option :value="30">30 per halaman</option>
                <option :value="50">50 per halaman</option>
            </select>
            <button class="btn btn-outline" type="button" @click="reset">Reset</button>
        </div>

        <div class="table-wrap">
            <table class="table">
                <thead>
                    <tr>
                        <th>Waktu</th>
                        <th>Produk</th>
                        <th>Jenis</th>
                        <th class="num">Perubahan</th>
                        <th class="num">Sebelum</th>
                        <th class="num">Sesudah</th>
                        <th>Referensi</th>
                        <th>Keterangan</th>
                        <th>Oleh</th>
                    </tr>
                </thead>
                <tbody>
                    <tr v-if="loading && items.length === 0">
                        <td colspan="9" class="loading-line">Memuat data...</td>
                    </tr>
                    <tr v-else-if="items.length === 0">
                        <td colspan="9" class="table-empty">Belum ada riwayat yang cocok.</td>
                    </tr>
                    <tr v-for="item in items" :key="item.id">
                        <td class="nowrap">{{ formatDateTime(item.created_at) }}</td>
                        <td>
                            <RouterLink v-if="item.product" :to="`${base}/products/${item.product.id}`">
                                <strong>{{ item.product.name }}</strong>
                            </RouterLink>
                            <div v-if="item.product" class="hint">{{ item.product.code }}</div>
                        </td>
                        <td><span class="badge" :class="typeBadge[item.type] ?? 'badge-muted'">{{ labelOf(item.type) }}</span></td>
                        <td class="num" :class="item.quantity_change > 0 ? 'change-plus' : 'change-minus'">
                            {{ item.quantity_change > 0 ? '+' : '' }}{{ formatNumber(item.quantity_change) }}
                        </td>
                        <td class="num">{{ formatNumber(item.stock_before) }}</td>
                        <td class="num">{{ formatNumber(item.stock_after) }}</td>
                        <td class="nowrap">{{ item.reference?.label ?? '-' }}</td>
                        <td>{{ item.note || '-' }}</td>
                        <td class="nowrap">{{ item.user ?? 'Sistem' }}</td>
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
    </section>
</template>
