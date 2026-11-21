<script setup>
import { computed, onMounted, reactive, ref, watch } from 'vue';
import http from '@/lib/http';
import AppIcon from '@/components/common/AppIcon.vue';
import AppPagination from '@/components/common/AppPagination.vue';
import SearchSelect from '@/components/common/SearchSelect.vue';
import StatusBadge from '@/components/common/StatusBadge.vue';
import ExportButtons from '@/components/reports/ExportButtons.vue';
import { useToast } from '@/composables/useToast';
import { useAuthStore } from '@/stores/auth';
import { debounce, errorMessage, formatNumber, formatRupiah } from '@/lib/utils';

const auth = useAuthStore();
const toast = useToast();

const base = computed(() => (auth.role === 'superadmin' ? '/superadmin' : '/admin-master'));

const lookups = reactive({ categories: [], product_types: [], brands: [] });
const items = ref([]);
const summary = ref({ total: 0, tersedia: 0, menipis: 0, habis: 0, nonaktif: 0 });
const loading = ref(false);
const meta = reactive({ page: 1, lastPage: 1, total: 0, from: 0, to: 0 });
const sortKey = ref('name:asc');
const filters = reactive({
    search: '',
    category_id: null,
    brand_id: null,
    product_type_id: null,
    status: '',
    perPage: 15,
});
let requestId = 0;

const chips = computed(() => [
    { value: '', label: 'Semua', count: summary.value.total },
    { value: 'tersedia', label: 'Tersedia', count: summary.value.tersedia },
    { value: 'menipis', label: 'Hampir habis', count: summary.value.menipis },
    { value: 'habis', label: 'Habis', count: summary.value.habis },
    { value: 'nonaktif', label: 'Tidak aktif', count: summary.value.nonaktif },
]);

const exportParams = computed(() => {
    const [sort, direction] = sortKey.value.split(':');

    return {
        search: filters.search || undefined,
        category_id: filters.category_id ?? undefined,
        brand_id: filters.brand_id ?? undefined,
        product_type_id: filters.product_type_id ?? undefined,
        status: filters.status || undefined,
        sort,
        direction,
    };
});

async function load() {
    const current = ++requestId;
    loading.value = true;

    const [sort, direction] = sortKey.value.split(':');

    try {
        const { data } = await http.get('/reports/inventory', {
            params: {
                page: meta.page,
                search: filters.search || undefined,
                category_id: filters.category_id ?? undefined,
                brand_id: filters.brand_id ?? undefined,
                product_type_id: filters.product_type_id ?? undefined,
                status: filters.status || undefined,
                sort,
                direction,
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
        summary.value = data.summary;
        meta.page = data.current_page;
        meta.lastPage = data.last_page;
        meta.total = data.total;
        meta.from = data.from ?? 0;
        meta.to = data.to ?? 0;
    } catch (error) {
        if (current === requestId) {
            toast.error(errorMessage(error, 'Gagal memuat laporan barang.'));
        }
    } finally {
        if (current === requestId) {
            loading.value = false;
        }
    }
}

watch(() => filters.search, debounce(() => {
    meta.page = 1;
    load();
}));

watch(
    () => [filters.category_id, filters.brand_id, filters.product_type_id, filters.status, filters.perPage, sortKey.value],
    () => {
        meta.page = 1;
        load();
    },
);

onMounted(async () => {
    try {
        const { data } = await http.get('/lookups');
        lookups.categories = data.categories;
        lookups.product_types = data.product_types;
        lookups.brands = data.brands;
    } catch {
        // filter master tidak tersedia, laporan tetap dapat dipakai
    }

    load();
});

function changePage(page) {
    meta.page = page;
    load();
}

function reset() {
    filters.search = '';
    filters.category_id = null;
    filters.brand_id = null;
    filters.product_type_id = null;
    filters.status = '';
    sortKey.value = 'name:asc';
}
</script>

<template>
    <section>
        <div class="page-header">
            <div>
                <h1>Laporan Barang</h1>
                <p>Kondisi stok seluruh barang. Hitungan di bawah mengikuti pencarian, kategori, merek, dan tipe yang dipilih.</p>
            </div>
            <div class="page-actions">
                <ExportButtons endpoint="/reports/inventory/export" :params="exportParams" />
            </div>
        </div>

        <div class="status-chips">
            <button
                v-for="chip in chips"
                :key="chip.value"
                type="button"
                class="status-chip"
                :class="{ 'status-chip--active': filters.status === chip.value }"
                @click="filters.status = chip.value"
            >
                {{ chip.label }} ({{ formatNumber(chip.count) }})
            </button>
        </div>

        <div class="toolbar">
            <div class="search-box">
                <span class="search-box__icon"><AppIcon name="search" :size="18" /></span>
                <input v-model="filters.search" class="input" type="search" placeholder="Cari nama, kode, atau barcode..." />
            </div>
            <div class="filter"><SearchSelect v-model="filters.category_id" :options="lookups.categories" placeholder="Semua kategori" search-placeholder="Cari kategori..." /></div>
            <div class="filter"><SearchSelect v-model="filters.brand_id" :options="lookups.brands" placeholder="Semua merek" search-placeholder="Cari merek..." /></div>
            <div class="filter"><SearchSelect v-model="filters.product_type_id" :options="lookups.product_types" placeholder="Semua tipe" search-placeholder="Cari tipe..." /></div>
        </div>

        <div class="toolbar">
            <select v-model="sortKey" class="input filter" aria-label="Urutkan">
                <option value="name:asc">Nama A-Z</option>
                <option value="name:desc">Nama Z-A</option>
                <option value="stock:asc">Stok tersedikit</option>
                <option value="stock:desc">Stok terbanyak</option>
                <option value="selling_price:asc">Harga termurah</option>
                <option value="selling_price:desc">Harga termahal</option>
            </select>
            <select v-model.number="filters.perPage" class="input filter" aria-label="Jumlah per halaman">
                <option :value="15">15 per halaman</option>
                <option :value="30">30 per halaman</option>
                <option :value="50">50 per halaman</option>
            </select>
            <button class="btn btn-outline" type="button" @click="reset">Reset Filter</button>
        </div>

        <div class="table-wrap" :style="{ opacity: loading && items.length ? 0.6 : 1 }">
            <table class="table">
                <thead>
                    <tr>
                        <th>Kode</th>
                        <th>Nama Barang</th>
                        <th>Kategori</th>
                        <th>Tipe</th>
                        <th>Merek</th>
                        <th class="num">Harga Jual</th>
                        <th class="num">Stok</th>
                        <th class="num">Min.</th>
                        <th>Satuan</th>
                        <th>Status</th>
                    </tr>
                </thead>
                <tbody>
                    <tr v-if="loading && items.length === 0">
                        <td colspan="10" class="loading-line">Memuat data...</td>
                    </tr>
                    <tr v-else-if="items.length === 0">
                        <td colspan="10" class="table-empty">Tidak ada barang yang cocok.</td>
                    </tr>
                    <tr v-for="item in items" :key="item.id">
                        <td class="nowrap muted">{{ item.code }}</td>
                        <td><RouterLink :to="`${base}/products/${item.id}`"><strong>{{ item.name }}</strong></RouterLink></td>
                        <td>{{ item.category ?? '-' }}</td>
                        <td>{{ item.product_type ?? '-' }}</td>
                        <td>{{ item.brand ?? '-' }}</td>
                        <td class="num">{{ formatRupiah(item.selling_price) }}</td>
                        <td class="num"><strong>{{ formatNumber(item.stock) }}</strong></td>
                        <td class="num muted">{{ formatNumber(item.min_stock) }}</td>
                        <td>{{ item.unit ?? '-' }}</td>
                        <td>
                            <span v-if="item.status === 'nonaktif'" class="badge badge-muted">Nonaktif</span>
                            <StatusBadge v-else kind="stock" :value="item.status" />
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
    </section>
</template>
