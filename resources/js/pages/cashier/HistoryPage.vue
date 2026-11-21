<script setup>
import { onMounted, reactive, ref, watch } from 'vue';
import http from '@/lib/http';
import AppIcon from '@/components/common/AppIcon.vue';
import AppModal from '@/components/common/AppModal.vue';
import AppPagination from '@/components/common/AppPagination.vue';
import OrderDetailPanel from '@/components/pos/OrderDetailPanel.vue';
import OrderStatusBadge from '@/components/store/OrderStatusBadge.vue';
import { useToast } from '@/composables/useToast';
import { debounce, errorMessage, formatDateTime, formatNumber, formatRupiah } from '@/lib/utils';

const toast = useToast();

const items = ref([]);
const loading = ref(false);
const meta = reactive({ page: 1, lastPage: 1, total: 0, from: 0, to: 0 });
const filters = reactive({ status: 'closed', search: '', date_from: '', date_to: '', perPage: 10 });
const detail = reactive({ open: false, loading: false, order: null });
let requestId = 0;

async function load() {
    const current = ++requestId;
    loading.value = true;

    try {
        const { data } = await http.get('/kasir/orders', {
            params: {
                page: meta.page,
                status: filters.status || undefined,
                search: filters.search || undefined,
                date_from: filters.date_from || undefined,
                date_to: filters.date_to || undefined,
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
            toast.error(error.response?.status === 422 ? 'Filter tanggal tidak valid.' : errorMessage(error, 'Gagal memuat riwayat.'));
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
    () => [filters.status, filters.date_from, filters.date_to, filters.perPage],
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

function resetFilters() {
    filters.status = 'closed';
    filters.search = '';
    filters.date_from = '';
    filters.date_to = '';
}

async function openDetail(item) {
    detail.open = true;
    detail.loading = true;
    detail.order = null;

    try {
        const { data } = await http.get(`/kasir/orders/${item.id}`);
        detail.order = data.data;
    } catch (error) {
        toast.error(errorMessage(error));
        detail.open = false;
    } finally {
        detail.loading = false;
    }
}
</script>

<template>
    <section>
        <div class="page-header">
            <div>
                <h1>Riwayat Transaksi</h1>
                <p>Transaksi yang Anda tangani.</p>
            </div>
        </div>

        <div class="toolbar">
            <div class="search-box">
                <span class="search-box__icon"><AppIcon name="search" :size="18" /></span>
                <input v-model="filters.search" class="input" type="search" placeholder="Cari nomor, nama, atau telepon..." />
            </div>
            <select v-model="filters.status" class="input filter" aria-label="Filter status">
                <option value="closed">Selesai &amp; dibatalkan</option>
                <option value="SELESAI">Selesai</option>
                <option value="DIBATALKAN">Dibatalkan</option>
                <option value="MENUNGGU">Menunggu pembayaran</option>
            </select>
            <input v-model="filters.date_from" class="input filter" type="date" aria-label="Dari tanggal" />
            <input v-model="filters.date_to" class="input filter" type="date" aria-label="Sampai tanggal" />
            <select v-model.number="filters.perPage" class="input filter" aria-label="Jumlah per halaman">
                <option :value="10">10 per halaman</option>
                <option :value="25">25 per halaman</option>
                <option :value="50">50 per halaman</option>
            </select>
            <button class="btn btn-outline" type="button" @click="resetFilters">Reset</button>
        </div>

        <div class="table-wrap">
            <table class="table">
                <thead>
                    <tr>
                        <th>No. Pesanan</th>
                        <th>Customer</th>
                        <th class="num">Barang</th>
                        <th class="num">Total</th>
                        <th>Status</th>
                        <th>Dipesan</th>
                        <th class="num">Aksi</th>
                    </tr>
                </thead>
                <tbody>
                    <tr v-if="loading && items.length === 0">
                        <td colspan="7" class="loading-line">Memuat data...</td>
                    </tr>
                    <tr v-else-if="items.length === 0">
                        <td colspan="7" class="table-empty">Belum ada transaksi yang cocok.</td>
                    </tr>
                    <tr v-for="item in items" :key="item.id">
                        <td class="nowrap"><strong>{{ item.order_number }}</strong></td>
                        <td>{{ item.customer_name }}<div class="hint">{{ item.customer_phone }}</div></td>
                        <td class="num">{{ formatNumber(item.total_quantity) }}</td>
                        <td class="num">{{ formatRupiah(item.total_amount) }}</td>
                        <td><OrderStatusBadge :status="item.status" :label="item.status_label" /></td>
                        <td class="nowrap">{{ formatDateTime(item.created_at) }}</td>
                        <td>
                            <div class="table-actions">
                                <button class="btn btn-outline btn-sm" type="button" @click="openDetail(item)">
                                    <AppIcon name="eye" :size="16" /> Detail
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

        <AppModal v-model="detail.open" title="Detail Transaksi" width="920px">
            <p v-if="detail.loading" class="loading-line" style="margin: 0">Memuat detail...</p>
            <OrderDetailPanel v-else-if="detail.order" :order="detail.order" readonly />
        </AppModal>
    </section>
</template>
