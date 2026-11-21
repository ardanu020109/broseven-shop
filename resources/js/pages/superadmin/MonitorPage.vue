<script setup>
import { computed, onBeforeUnmount, onMounted, reactive, ref, watch } from 'vue';
import http from '@/lib/http';
import AppIcon from '@/components/common/AppIcon.vue';
import AppModal from '@/components/common/AppModal.vue';
import AppPagination from '@/components/common/AppPagination.vue';
import OrderStatusBadge from '@/components/store/OrderStatusBadge.vue';
import QrCode from '@/components/store/QrCode.vue';
import { useToast } from '@/composables/useToast';
import { debounce, errorMessage, formatDateTime, formatNumber, formatRupiah } from '@/lib/utils';

const POLL_MS = 20000;

const toast = useToast();

const items = ref([]);
const counts = ref({ DRAFT: 0, MENUNGGU: 0, SELESAI: 0, DIBATALKAN: 0 });
const cashiers = ref([]);
const loading = ref(false);
const meta = reactive({ page: 1, lastPage: 1, total: 0, from: 0, to: 0 });
const filters = reactive({ status: '', cashier_id: '', search: '', date_from: '', date_to: '', perPage: 10 });
const detail = reactive({ open: false, loading: false, data: null });

let requestId = 0;
let timer = null;

const chips = computed(() => {
    const total = Object.values(counts.value).reduce((sum, value) => sum + value, 0);

    return [
        { value: '', label: 'Semua', count: total },
        { value: 'DRAFT', label: 'Draft', count: counts.value.DRAFT },
        { value: 'MENUNGGU', label: 'Menunggu', count: counts.value.MENUNGGU },
        { value: 'SELESAI', label: 'Selesai', count: counts.value.SELESAI },
        { value: 'DIBATALKAN', label: 'Dibatalkan', count: counts.value.DIBATALKAN },
    ];
});

function barcodeState(item) {
    if (item.status === 'DRAFT') {
        return { klass: 'badge-success', text: 'Aktif' };
    }

    if (item.barcode_used) {
        return { klass: 'badge-muted', text: 'Terpakai' };
    }

    return { klass: 'badge-muted', text: 'Tidak berlaku' };
}

async function load(silent = false) {
    const current = ++requestId;

    if (!silent) {
        loading.value = true;
    }

    try {
        const { data } = await http.get('/monitor/orders', {
            params: {
                page: meta.page,
                status: filters.status || undefined,
                cashier_id: filters.cashier_id || undefined,
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
            await load(silent);
            return;
        }

        items.value = data.data;
        counts.value = data.counts;
        meta.page = data.current_page;
        meta.lastPage = data.last_page;
        meta.total = data.total;
        meta.from = data.from ?? 0;
        meta.to = data.to ?? 0;
    } catch (error) {
        if (!silent && current === requestId) {
            toast.error(error.response?.status === 422 ? 'Filter tanggal tidak valid.' : errorMessage(error, 'Gagal memuat data.'));
        }
    } finally {
        if (current === requestId) {
            loading.value = false;
        }
    }
}

function changePage(page) {
    meta.page = page;
    load();
}

function resetFilters() {
    filters.status = '';
    filters.cashier_id = '';
    filters.search = '';
    filters.date_from = '';
    filters.date_to = '';
}

async function openDetail(item) {
    detail.open = true;
    detail.loading = true;
    detail.data = null;

    try {
        const { data } = await http.get(`/monitor/orders/${item.id}`);
        detail.data = data.data;
    } catch (error) {
        toast.error(errorMessage(error));
        detail.open = false;
    } finally {
        detail.loading = false;
    }
}

watch(() => filters.search, debounce(() => {
    meta.page = 1;
    load();
}));

watch(
    () => [filters.status, filters.cashier_id, filters.date_from, filters.date_to, filters.perPage],
    () => {
        meta.page = 1;
        load();
    },
);

onMounted(async () => {
    try {
        const { data } = await http.get('/lookups/cashiers');
        cashiers.value = data.data;
    } catch {
        cashiers.value = [];
    }

    load();

    timer = setInterval(() => {
        if (!document.hidden) {
            load(true);
        }
    }, POLL_MS);
});

onBeforeUnmount(() => clearInterval(timer));
</script>

<template>
    <section>
        <div class="page-header">
            <div>
                <h1>Pelayanan Kasir</h1>
                <p>Pantau seluruh pesanan customer. Halaman ini hanya untuk memantau; persetujuan tetap wewenang Kasir.</p>
            </div>
            <div class="page-actions">
                <button class="btn btn-outline" type="button" :disabled="loading" @click="load()">
                    <AppIcon name="refresh" :size="18" /> Muat Ulang
                </button>
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
                <input v-model="filters.search" class="input" type="search" placeholder="Cari nomor, nama, atau telepon..." />
            </div>
            <select v-model="filters.cashier_id" class="input filter" aria-label="Filter kasir">
                <option value="">Semua kasir</option>
                <option v-for="cashier in cashiers" :key="cashier.id" :value="cashier.id">{{ cashier.name }}</option>
            </select>
            <input v-model="filters.date_from" class="input filter" type="date" aria-label="Dari tanggal" />
            <input v-model="filters.date_to" class="input filter" type="date" aria-label="Sampai tanggal" />
            <button class="btn btn-outline" type="button" @click="resetFilters">Reset</button>
        </div>

        <div class="table-wrap">
            <table class="table">
                <thead>
                    <tr>
                        <th>No. Pesanan</th>
                        <th>Customer</th>
                        <th>Barcode</th>
                        <th>Produk</th>
                        <th class="num">Jumlah</th>
                        <th class="num">Total</th>
                        <th>Status</th>
                        <th>Kasir</th>
                        <th>Waktu</th>
                        <th class="num">Aksi</th>
                    </tr>
                </thead>
                <tbody>
                    <tr v-if="loading && items.length === 0">
                        <td colspan="10" class="loading-line">Memuat data...</td>
                    </tr>
                    <tr v-else-if="items.length === 0">
                        <td colspan="10" class="table-empty">Belum ada pesanan yang cocok.</td>
                    </tr>
                    <tr v-for="item in items" :key="item.id">
                        <td class="nowrap"><strong>{{ item.order_number }}</strong></td>
                        <td>{{ item.customer_name }}<div class="hint">{{ item.customer_phone }}</div></td>
                        <td><span class="badge" :class="barcodeState(item).klass">{{ barcodeState(item).text }}</span></td>
                        <td class="muted">{{ item.items_summary }}</td>
                        <td class="num">{{ formatNumber(item.total_quantity) }}</td>
                        <td class="num">{{ formatRupiah(item.total_amount) }}</td>
                        <td><OrderStatusBadge :status="item.status" :label="item.status_label" /></td>
                        <td>{{ item.cashier ?? '-' }}</td>
                        <td>
                            <div class="nowrap">Dipesan: {{ formatDateTime(item.created_at) }}</div>
                            <div v-if="item.approved_at" class="hint nowrap">Disetujui: {{ formatDateTime(item.approved_at) }}</div>
                            <div v-if="item.completed_at" class="hint nowrap">Selesai: {{ formatDateTime(item.completed_at) }}</div>
                            <div v-if="item.cancelled_at" class="hint nowrap">Dibatalkan: {{ formatDateTime(item.cancelled_at) }}</div>
                        </td>
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

        <AppModal v-model="detail.open" title="Detail Pesanan" width="900px">
            <p v-if="detail.loading" class="loading-line" style="margin: 0">Memuat detail...</p>

            <template v-else-if="detail.data">
                <div class="order-head">
                    <div>
                        <h2 class="order-no">{{ detail.data.order_number }}</h2>
                        <OrderStatusBadge :status="detail.data.status" :label="detail.data.status_label" />
                        <p class="hint" style="margin: 6px 0 0">{{ detail.data.status_description }}</p>
                    </div>
                    <div v-if="detail.data.barcode_code" style="text-align: center">
                        <QrCode :value="detail.data.barcode_code" :size="130" />
                        <div class="hint">Barcode aktif</div>
                    </div>
                </div>

                <dl class="kv" style="margin-bottom: 16px">
                    <div><dt>Customer</dt><dd>{{ detail.data.customer_name }}</dd></div>
                    <div><dt>Telepon</dt><dd>{{ detail.data.customer_phone }}</dd></div>
                    <div><dt>Kasir</dt><dd>{{ detail.data.cashier ?? '-' }}</dd></div>
                    <div><dt>Pembayaran</dt><dd>{{ detail.data.payment_method_label }}</dd></div>
                    <div><dt>Dipesan</dt><dd>{{ formatDateTime(detail.data.created_at) }}</dd></div>
                    <div v-if="detail.data.approved_at"><dt>Disetujui</dt><dd>{{ formatDateTime(detail.data.approved_at) }}</dd></div>
                    <div v-if="detail.data.completed_at"><dt>Selesai</dt><dd>{{ formatDateTime(detail.data.completed_at) }}</dd></div>
                    <div v-if="detail.data.service_minutes !== null"><dt>Waktu pelayanan</dt><dd>{{ formatNumber(detail.data.service_minutes) }} menit</dd></div>
                    <div v-if="detail.data.cancelled_at"><dt>Dibatalkan</dt><dd>{{ formatDateTime(detail.data.cancelled_at) }}<span v-if="detail.data.cancelled_by" class="muted"> oleh {{ detail.data.cancelled_by }}</span></dd></div>
                    <div v-if="detail.data.cancel_reason" style="grid-column: 1 / -1"><dt>Alasan pembatalan</dt><dd>{{ detail.data.cancel_reason }}</dd></div>
                    <div v-if="detail.data.notes" style="grid-column: 1 / -1"><dt>Catatan customer</dt><dd>{{ detail.data.notes }}</dd></div>
                </dl>

                <div class="table-wrap">
                    <table class="table">
                        <thead>
                            <tr>
                                <th>Barang</th>
                                <th class="num">Harga</th>
                                <th class="num">Jumlah</th>
                                <th v-if="detail.data.status === 'SELESAI'" class="num">Diretur</th>
                                <th class="num">Subtotal</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr v-for="line in detail.data.items" :key="line.id">
                                <td><strong>{{ line.name }}</strong><div class="hint">{{ line.code }}</div></td>
                                <td class="num">{{ formatRupiah(line.unit_price) }}</td>
                                <td class="num">{{ formatNumber(line.quantity) }}</td>
                                <td v-if="detail.data.status === 'SELESAI'" class="num">{{ formatNumber(line.returned_quantity) }}</td>
                                <td class="num">{{ formatRupiah(line.subtotal) }}</td>
                            </tr>
                        </tbody>
                    </table>
                </div>

                <div class="summary__row summary__row--total">
                    <span>Total pembayaran</span>
                    <span>{{ formatRupiah(detail.data.total_amount) }}</span>
                </div>
                <div v-if="detail.data.returned_amount > 0" class="summary__row">
                    <span>Nilai retur</span>
                    <strong>{{ formatRupiah(detail.data.returned_amount) }}</strong>
                </div>

                <template v-if="detail.data.returns.length">
                    <h3 style="margin: 20px 0 10px">Retur</h3>
                    <div v-for="item in detail.data.returns" :key="item.id" class="return-card">
                        <strong>{{ item.return_number }}</strong>
                        <span class="muted"> · {{ formatDateTime(item.created_at) }}<span v-if="item.user"> · {{ item.user }}</span></span>
                        <div class="hint">Alasan: {{ item.reason }}</div>
                        <strong>{{ formatRupiah(item.total_amount) }}</strong>
                    </div>
                </template>

                <h3 style="margin: 20px 0 10px">Riwayat Status</h3>
                <ul class="history">
                    <li v-for="(history, index) in detail.data.histories" :key="index">
                        <strong>{{ history.to_label }}</strong>
                        <span v-if="history.user" class="muted"> · {{ history.user }}</span>
                        <small>{{ formatDateTime(history.created_at) }}</small>
                        <small v-if="history.note">{{ history.note }}</small>
                    </li>
                </ul>
            </template>
        </AppModal>
    </section>
</template>
