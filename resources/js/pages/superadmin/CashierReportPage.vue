<script setup>
import { onMounted, reactive, ref, watch } from 'vue';
import http from '@/lib/http';
import AppPagination from '@/components/common/AppPagination.vue';
import PeriodFilter from '@/components/common/PeriodFilter.vue';
import ExportButtons from '@/components/reports/ExportButtons.vue';
import OrderStatusBadge from '@/components/store/OrderStatusBadge.vue';
import { useToast } from '@/composables/useToast';
import { defaultPeriod, describePeriod, periodParams } from '@/lib/period';
import { errorMessage, formatDateTime, formatNumber, formatRupiah } from '@/lib/utils';

const toast = useToast();

const period = ref(defaultPeriod('month'));
const cashierId = ref('');
const cashiers = ref([]);
const report = ref(null);
const loading = ref(true);
const activities = ref([]);
const activitiesLoading = ref(false);
const meta = reactive({ page: 1, lastPage: 1, total: 0, from: 0, to: 0 });
let reportRequest = 0;
let activityRequest = 0;

function params() {
    return { ...periodParams(period.value), cashier_id: cashierId.value || undefined };
}

function minutes(value) {
    return value === null || value === undefined ? '-' : `${formatNumber(value)} mnt`;
}

async function loadReport() {
    const current = ++reportRequest;
    loading.value = true;

    try {
        const { data } = await http.get('/reports/cashiers', { params: params() });

        if (current === reportRequest) {
            report.value = data;
        }
    } catch (error) {
        if (current === reportRequest) {
            toast.error(error.response?.status === 422 ? 'Periode atau filter tidak valid.' : errorMessage(error, 'Gagal memuat laporan.'));
        }
    } finally {
        if (current === reportRequest) {
            loading.value = false;
        }
    }
}

async function loadActivities() {
    const current = ++activityRequest;
    activitiesLoading.value = true;

    try {
        const { data } = await http.get('/reports/cashiers/activities', { params: { ...params(), page: meta.page, per_page: 10 } });

        if (current !== activityRequest) {
            return;
        }

        activities.value = data.data;
        meta.page = data.current_page;
        meta.lastPage = data.last_page;
        meta.total = data.total;
        meta.from = data.from ?? 0;
        meta.to = data.to ?? 0;
    } catch {
        if (current === activityRequest) {
            activities.value = [];
        }
    } finally {
        if (current === activityRequest) {
            activitiesLoading.value = false;
        }
    }
}

function reloadAll() {
    meta.page = 1;
    loadReport();
    loadActivities();
}

function changePage(page) {
    meta.page = page;
    loadActivities();
}

watch([period, cashierId], reloadAll);

onMounted(async () => {
    try {
        const { data } = await http.get('/lookups/cashiers');
        cashiers.value = data.data;
    } catch {
        cashiers.value = [];
    }

    reloadAll();
});
</script>

<template>
    <section>
        <div class="page-header">
            <div>
                <h1>Laporan Kasir</h1>
                <p>Kinerja setiap kasir. Periode: <strong>{{ describePeriod(report?.period) }}</strong>. Waktu pelayanan = rata-rata dari persetujuan sampai penyelesaian.</p>
            </div>
            <div class="page-actions">
                <ExportButtons endpoint="/reports/cashiers/export" :params="params()" />
            </div>
        </div>

        <div class="toolbar">
            <PeriodFilter v-model="period" />
            <select v-model="cashierId" class="input filter" aria-label="Filter kasir">
                <option value="">Semua kasir</option>
                <option v-for="cashier in cashiers" :key="cashier.id" :value="cashier.id">{{ cashier.name }}</option>
            </select>
        </div>

        <div class="table-wrap" :style="{ opacity: loading ? 0.6 : 1 }">
            <table class="table">
                <thead>
                    <tr>
                        <th>Kasir</th>
                        <th class="num">Diproses</th>
                        <th class="num">Selesai</th>
                        <th class="num">Pendapatan</th>
                        <th class="num">Rata-rata</th>
                        <th class="num">Barang Terjual</th>
                        <th class="num">Dibatalkan</th>
                        <th class="num">Retur</th>
                        <th class="num">Pendapatan Bersih</th>
                        <th class="num">Waktu Pelayanan</th>
                    </tr>
                </thead>
                <tbody>
                    <tr v-if="loading && !report">
                        <td colspan="10" class="loading-line">Memuat laporan...</td>
                    </tr>
                    <tr v-else-if="!report || report.rows.length === 0">
                        <td colspan="10" class="table-empty">Belum ada data kasir pada periode ini.</td>
                    </tr>
                    <tr v-for="row in report?.rows ?? []" :key="row.id">
                        <td>
                            <strong>{{ row.name }}</strong>
                            <span v-if="!row.is_active" class="badge badge-muted" style="margin-left: 6px">Nonaktif</span>
                        </td>
                        <td class="num">{{ formatNumber(row.handled) }}</td>
                        <td class="num">{{ formatNumber(row.completed) }}</td>
                        <td class="num">{{ formatRupiah(row.revenue) }}</td>
                        <td class="num">{{ formatRupiah(row.average) }}</td>
                        <td class="num">{{ formatNumber(row.units_sold) }}</td>
                        <td class="num">{{ formatNumber(row.cancelled) }}</td>
                        <td class="num">{{ formatNumber(row.returns_count) }} ({{ formatRupiah(row.returns_total) }})</td>
                        <td class="num">{{ formatRupiah(row.net_revenue) }}</td>
                        <td class="num">{{ minutes(row.avg_service_minutes) }}</td>
                    </tr>
                </tbody>
                <tfoot v-if="report && report.rows.length > 0">
                    <tr>
                        <td>Total</td>
                        <td class="num">{{ formatNumber(report.totals.handled) }}</td>
                        <td class="num">{{ formatNumber(report.totals.completed) }}</td>
                        <td class="num">{{ formatRupiah(report.totals.revenue) }}</td>
                        <td class="num">{{ formatRupiah(report.totals.average) }}</td>
                        <td class="num">{{ formatNumber(report.totals.units_sold) }}</td>
                        <td class="num">{{ formatNumber(report.totals.cancelled) }}</td>
                        <td class="num">{{ formatNumber(report.totals.returns_count) }} ({{ formatRupiah(report.totals.returns_total) }})</td>
                        <td class="num">{{ formatRupiah(report.totals.net_revenue) }}</td>
                        <td class="num">{{ minutes(report.totals.avg_service_minutes) }}</td>
                    </tr>
                </tfoot>
            </table>
        </div>

        <div class="card" style="margin-top: 20px">
            <h3>Riwayat Aktivitas Kasir</h3>
            <div class="table-wrap">
                <table class="table">
                    <thead>
                        <tr>
                            <th>Waktu</th>
                            <th>Kasir</th>
                            <th>Pesanan</th>
                            <th>Customer</th>
                            <th>Perubahan Status</th>
                            <th>Keterangan</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr v-if="activitiesLoading && activities.length === 0">
                            <td colspan="6" class="loading-line">Memuat data...</td>
                        </tr>
                        <tr v-else-if="activities.length === 0">
                            <td colspan="6" class="table-empty">Belum ada aktivitas pada periode ini.</td>
                        </tr>
                        <tr v-for="item in activities" :key="item.id">
                            <td class="nowrap">{{ formatDateTime(item.created_at) }}</td>
                            <td>{{ item.cashier }}</td>
                            <td class="nowrap"><strong>{{ item.order_number }}</strong></td>
                            <td>{{ item.customer_name }}</td>
                            <td class="nowrap">
                                <span class="muted">{{ item.from_label ?? '-' }} →</span>
                                <OrderStatusBadge :status="item.to_status" :label="item.to_label" />
                            </td>
                            <td>{{ item.note || '-' }}</td>
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
