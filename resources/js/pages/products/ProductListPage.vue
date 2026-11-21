<script setup>
import { computed, onMounted, reactive, ref, watch } from 'vue';
import { useRouter } from 'vue-router';
import http from '@/lib/http';
import AppIcon from '@/components/common/AppIcon.vue';
import AppPagination from '@/components/common/AppPagination.vue';
import ConfirmDialog from '@/components/common/ConfirmDialog.vue';
import SearchSelect from '@/components/common/SearchSelect.vue';
import StatusBadge from '@/components/common/StatusBadge.vue';
import { useToast } from '@/composables/useToast';
import { useAuthStore } from '@/stores/auth';
import { debounce, errorMessage, formatNumber, formatRupiah } from '@/lib/utils';

const auth = useAuthStore();
const router = useRouter();
const toast = useToast();

const base = computed(() => (auth.role === 'superadmin' ? '/superadmin' : '/admin-master'));
const canManage = computed(() => auth.role === 'admin_master');

const sortOptions = {
    latest: { sort: 'created_at', direction: 'desc' },
    name_asc: { sort: 'name', direction: 'asc' },
    price_asc: { sort: 'selling_price', direction: 'asc' },
    price_desc: { sort: 'selling_price', direction: 'desc' },
    stock_asc: { sort: 'stock', direction: 'asc' },
};

const lookups = reactive({ categories: [], product_types: [], brands: [], units: [] });
const items = ref([]);
const loading = ref(false);
const meta = reactive({ page: 1, lastPage: 1, total: 0, from: 0, to: 0 });
const filters = reactive({
    search: '',
    category_id: null,
    brand_id: null,
    product_type_id: null,
    stock_status: '',
    is_active: '',
    sort: 'latest',
    perPage: 10,
});
const confirm = reactive({ open: false, item: null, loading: false });
let requestId = 0;

async function load() {
    const current = ++requestId;
    loading.value = true;

    try {
        const { data } = await http.get('/products', {
            params: {
                page: meta.page,
                search: filters.search || undefined,
                category_id: filters.category_id ?? undefined,
                brand_id: filters.brand_id ?? undefined,
                product_type_id: filters.product_type_id ?? undefined,
                stock_status: filters.stock_status || undefined,
                is_active: filters.is_active || undefined,
                ...sortOptions[filters.sort],
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
            toast.error(errorMessage(error, 'Gagal memuat data barang.'));
        }
    } finally {
        if (current === requestId) {
            loading.value = false;
        }
    }
}

async function loadLookups() {
    try {
        const { data } = await http.get('/lookups');
        Object.assign(lookups, data);
    } catch (error) {
        toast.error(errorMessage(error, 'Gagal memuat data filter.'));
    }
}

const onSearch = debounce(() => {
    meta.page = 1;
    load();
});

watch(() => filters.search, onSearch);
watch(
    () => [
        filters.category_id,
        filters.brand_id,
        filters.product_type_id,
        filters.stock_status,
        filters.is_active,
        filters.sort,
        filters.perPage,
    ],
    () => {
        meta.page = 1;
        load();
    },
);

onMounted(() => {
    loadLookups();
    load();
});

function changePage(page) {
    meta.page = page;
    load();
}

function resetFilters() {
    filters.search = '';
    filters.category_id = null;
    filters.brand_id = null;
    filters.product_type_id = null;
    filters.stock_status = '';
    filters.is_active = '';
    filters.sort = 'latest';
}

function askDelete(item) {
    confirm.item = item;
    confirm.open = true;
}

async function remove() {
    confirm.loading = true;

    try {
        const { data } = await http.delete(`/products/${confirm.item.id}`);
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
                <h1>{{ canManage ? 'Master Barang' : 'Data Produk' }}</h1>
                <p>{{ canManage ? 'Kelola seluruh barang, harga, dan stok.' : 'Daftar produk (hanya lihat).' }}</p>
            </div>
            <div v-if="canManage" class="page-actions">
                <button class="btn btn-primary" type="button" @click="router.push(`${base}/products/create`)">
                    <AppIcon name="plus" :size="18" /> Tambah Barang
                </button>
            </div>
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
            <select v-model="filters.stock_status" class="input filter" aria-label="Filter status stok">
                <option value="">Semua status stok</option>
                <option value="tersedia">Tersedia</option>
                <option value="menipis">Hampir habis</option>
                <option value="habis">Habis</option>
            </select>
            <select v-model="filters.is_active" class="input filter" aria-label="Filter status produk">
                <option value="">Aktif &amp; nonaktif</option>
                <option value="1">Aktif</option>
                <option value="0">Nonaktif</option>
            </select>
            <select v-model="filters.sort" class="input filter" aria-label="Urutkan">
                <option value="latest">Terbaru</option>
                <option value="name_asc">Nama A-Z</option>
                <option value="price_asc">Harga termurah</option>
                <option value="price_desc">Harga termahal</option>
                <option value="stock_asc">Stok tersedikit</option>
            </select>
            <select v-model.number="filters.perPage" class="input filter" aria-label="Jumlah per halaman">
                <option :value="5">5 per halaman</option>
                <option :value="10">10 per halaman</option>
                <option :value="25">25 per halaman</option>
                <option :value="50">50 per halaman</option>
            </select>
            <button class="btn btn-outline" type="button" @click="resetFilters">Reset Filter</button>
        </div>

        <div class="table-wrap">
            <table class="table">
                <thead>
                    <tr>
                        <th>Barang</th>
                        <th>Kode</th>
                        <th>Kategori</th>
                        <th>Merek</th>
                        <th class="num">Harga Jual</th>
                        <th class="num">Stok</th>
                        <th>Status Stok</th>
                        <th>Status</th>
                        <th class="num">Aksi</th>
                    </tr>
                </thead>
                <tbody>
                    <tr v-if="loading && items.length === 0">
                        <td colspan="9" class="loading-line">Memuat data...</td>
                    </tr>
                    <tr v-else-if="items.length === 0">
                        <td colspan="9" class="table-empty">Belum ada barang yang cocok.</td>
                    </tr>
                    <tr v-for="item in items" :key="item.id">
                        <td>
                            <div class="product-cell">
                                <span class="thumb">
                                    <img v-if="item.image_url" :src="item.image_url" :alt="item.name" loading="lazy" />
                                    <AppIcon v-else name="image" />
                                </span>
                                <strong>{{ item.name }}</strong>
                            </div>
                        </td>
                        <td class="nowrap muted">{{ item.code }}</td>
                        <td>{{ item.category?.name ?? '-' }}</td>
                        <td>{{ item.brand?.name ?? '-' }}</td>
                        <td class="num">{{ formatRupiah(item.selling_price) }}</td>
                        <td class="num">
                            {{ formatNumber(item.stock) }} {{ item.unit?.abbreviation || item.unit?.name || '' }}
                            <div class="hint">min. {{ formatNumber(item.min_stock) }}</div>
                        </td>
                        <td><StatusBadge kind="stock" :value="item.stock_status" /></td>
                        <td><StatusBadge :value="item.is_active" /></td>
                        <td>
                            <div class="table-actions">
                                <button class="btn btn-outline btn-sm" type="button" @click="router.push(`${base}/products/${item.id}`)">
                                    <AppIcon name="eye" :size="16" /> Detail
                                </button>
                                <template v-if="canManage">
                                    <button class="btn btn-outline btn-sm" type="button" @click="router.push(`${base}/products/${item.id}/edit`)">
                                        <AppIcon name="edit" :size="16" /> Edit
                                    </button>
                                    <button class="btn btn-outline-danger btn-sm" type="button" @click="askDelete(item)">
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

        <ConfirmDialog
            v-model="confirm.open"
            title="Hapus Barang"
            :message="`Hapus '${confirm.item?.name ?? ''}'? Barang diarsipkan (soft delete), riwayat stok dan transaksi tetap tersimpan.`"
            confirm-text="Ya, Hapus"
            danger
            :loading="confirm.loading"
            @confirm="remove"
        />
    </section>
</template>
