<script setup>
import { computed, onMounted, reactive, ref, watch } from 'vue';
import http from '@/lib/http';
import AppIcon from '@/components/common/AppIcon.vue';
import AppPagination from '@/components/common/AppPagination.vue';
import TrendChart from '@/components/charts/TrendChart.vue';
import PeriodFilter from '@/components/common/PeriodFilter.vue';
import ExportButtons from '@/components/reports/ExportButtons.vue';
import OrderStatusBadge from '@/components/store/OrderStatusBadge.vue';
import { useToast } from '@/composables/useToast';
import { bucketLabel, bucketTitle, defaultPeriod, describePeriod, periodParams } from '@/lib/period';
import { debounce, errorMessage, formatDateTime, formatNumber, formatRupiah } from '@/lib/utils';

const toast = useToast();

const period = ref(defaultPeriod('month'));
const status = ref('');
const search = ref('');
const report = ref(null);
const loading = ref(true);
const rows = ref([]);
const rowsLoading = ref(false);
const meta = reactive({ page: 1, lastPage: 1, total: 0, from: 0, to: 0 });
let reportRequest = 0;
let rowsRequest = 0;

function params() {
    return { ...periodParams(period.value), status: status.value || undefined };
}

const points = computed(() =>
    (report.value?.series ?? []).map((row) => ({
        label: bucketLabel(row.key, report.value.period.granularity),
        title: bucketTitle(row.key, report.value.period.granularity),
        value: row.revenue,
    })),
);

function minutes(value) {
    return value === null || value === undefined ? '-' : `${formatNumber(value)} mnt`;
}

async function loadReport() {
    const current = ++reportRequest;
    loading.value = true;

    try {
        const { data } = await http.get('/kasir/reports/summary', { params: periodParams(period.value) });

        if (current === reportRequest) {
            report.value = data;
        }
    } catch (error) {
        if (current === reportRequest) {
            toast.error(error.response?.status === 422 ? 'Periode tidak valid.' : errorMessage(error, 'Gagal memuat laporan.'));
        }
    } finally {
        if (current === reportRequest) {
            loading.value = false;
        }
    }
}

async function loadRows() {
    const current = ++rowsRequest;
    rowsLoading.value = true;

    try {
        const { data } = await http.get('/kasir/reports/transactions', {
            params: { ...params(), page: meta.page, search: search.value || undefined, per_page: 10 },
        });

        if (current !== rowsRequest) {
            return;
        }

        rows.value = data.data;
        meta.page = data.current_page;
        meta.lastPage = data.last_page;
        meta.total = data.total;
        meta.from = data.from ?? 0;
        meta.to = data.to ?? 0;
    } catch {
        if (current === rowsRequest) {
            rows.value = [];
        }
    } finally {
        if (current === rowsRequest) {
            rowsLoading.value = false;
        }
    }
}

function changePage(page) {
    meta.page = page;
    loadRows();
}

watch(period, () => {
    meta.page = 1;
    loadReport();
    loadRows();
});

watch(status, () => {
    meta.page = 1;
    loadRows();
});

watch(search, debounce(() => {
    meta.page = 1;
    loadRows();
}));

onMounted(() => {
    loadReport();
    loadRows();
});
</script>

<template>
    <section>
        <div class="page-header">
            <div>
                <h1>Laporan Saya</h1>
                <p>Transaksi yang Anda tangani. Periode: <strong>{{ describePeriod(report?.period) }}</strong></p>
            </div>
            <div class="page-actions">
                <ExportButtons endpoint="/kasir/reports/export" :params="params()" />
            </div>
        </div>

        <div class="toolbar">
            <PeriodFilter v-model="period" />
        </div>

        <div v-if="loading && !report" class="card loading-line">Memuat laporan...</div>

        <template v-else-if="report">
            <div class="stat-grid" :style="{ opacity: loading ? 0.6 : 1 }">
                <div class="stat-card stat-card--accent">
                    <div class="stat-card__label">Total Pendapatan</div>
                    <div class="stat-card__value">{{ formatRupiah(report.summary.revenue) }}</div>
                </div>
                <div class="stat-card">
                    <div class="stat-card__label">Transaksi Diproses</div>
                    <div class="stat-card__value">{{ formatNumber(report.summary.handled) }}</div>
                </div>
                <div class="stat-card stat-card--accent">
                    <div class="stat-card__label">Transaksi Selesai</div>
                    <div class="stat-card__value">{{ formatNumber(report.summary.completed) }}</div>
                </div>
                <div class="stat-card stat-card--danger">
                    <div class="stat-card__label">Dibatalkan</div>
                    <div class="stat-card__value">{{ formatNumber(report.summary.cancelled) }}</div>
                </div>
                <div class="stat-card">
                    <div class="stat-card__label">Produk Terjual</div>
                    <div class="stat-card__value">{{ formatNumber(report.summary.units_sold) }}</div>
                </div>
                <div class="stat-card">
                    <div class="stat-card__label">Rata-rata Transaksi</div>
                    <div class="stat-card__value">{{ formatRupiah(report.summary.average) }}</div>
                </div>
                <div class="stat-card stat-card--warn">
                    <div class="stat-card__label">Nilai Retur ({{ formatNumber(report.summary.returns_count) }})</div>
                    <div class="stat-card__value">{{ formatRupiah(report.summary.returns_total) }}</div>
                </div>
                <div class="stat-card stat-card--accent">
                    <div class="stat-card__label">Pendapatan Bersih</div>
                    <div class="stat-card__value">{{ formatRupiah(report.summary.net_revenue) }}</div>
                    <div class="hint">Pelayanan rata-rata {{ minutes(report.summary.avg_service_minutes) }}</div>
                </div>
            </div>

            <div class="card" style="margin-bottom: 20px">
                <h3>Grafik Pendapatan</h3>
                <TrendChart :points="points" type="bar" :formatter="formatRupiah" />
            </div>
        </template>

        <div class="card">
            <div class="page-header" style="margin-bottom: 12px">
                <h3 style="margin: 0">Riwayat Transaksi</h3>
                <div class="toolbar" style="margin: 0">
                    <select v-model="status" class="input filter" aria-label="Filter status">
                        <option value="">Selesai &amp; dibatalkan</option>
                        <option value="SELESAI">Selesai</option>
                        <option value="DIBATALKAN">Dibatalkan</option>
                    </select>
                    <div class="search-box" style="min-width: 220px">
                        <span class="search-box__icon"><AppIcon name="search" :size="18" /></span>
                        <input v-model="search" class="input" type="search" placeholder="Cari nomor atau nama..." />
                    </div>
                </div>
            </div>

            <div class="table-wrap">
                <table class="table">
                    <thead>
                        <tr>
                            <th>Waktu</th>
                            <th>No. Pesanan</th>
                            <th>Customer</th>
                            <th class="num">Barang</th>
                            <th class="num">Total</th>
                            <th>Status</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr v-if="rowsLoading && rows.length === 0">
                            <td colspan="6" class="loading-line">Memuat data...</td>
                        </tr>
                        <tr v-else-if="rows.length === 0">
                            <td colspan="6" class="table-empty">Belum ada transaksi pada periode ini.</td>
                        </tr>
                        <tr v-for="row in rows" :key="row.id">
                            <td class="nowrap">{{ formatDateTime(row.event_at) }}</td>
                            <td class="nowrap"><strong>{{ row.order_number }}</strong></td>
                            <td>{{ row.customer_name }}</td>
                            <td class="num">{{ formatNumber(row.total_quantity) }}</td>
                            <td class="num">{{ formatRupiah(row.total_amount) }}</td>
                            <td><OrderStatusBadge :status="row.status" :label="row.status_label" /></td>
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
        </div>
    </section>
</template>
