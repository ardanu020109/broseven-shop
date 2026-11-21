<script setup>
import { computed, onMounted, reactive, ref, watch } from "vue";
import http from "@/lib/http";
import AppIcon from "@/components/common/AppIcon.vue";
import AppPagination from "@/components/common/AppPagination.vue";
import TrendChart from "@/components/charts/TrendChart.vue";
import PeriodFilter from "@/components/common/PeriodFilter.vue";
import ExportButtons from "@/components/reports/ExportButtons.vue";
import { useToast } from "@/composables/useToast";
import {
    bucketLabel,
    bucketTitle,
    defaultPeriod,
    describePeriod,
    periodParams,
} from "@/lib/period";
import {
    debounce,
    errorMessage,
    formatDateTime,
    formatNumber,
    formatRupiah,
} from "@/lib/utils";

const toast = useToast();

const period = ref(defaultPeriod("month"));
const cashierId = ref("");
const cashiers = ref([]);
const report = ref(null);
const loading = ref(true);
const rows = ref([]);
const rowsLoading = ref(false);
const search = ref("");
const meta = reactive({ page: 1, lastPage: 1, total: 0, from: 0, to: 0 });
let reportRequest = 0;
let rowsRequest = 0;

function params() {
    return {
        ...periodParams(period.value),
        cashier_id: cashierId.value || undefined,
    };
}

async function loadReport() {
    const current = ++reportRequest;
    loading.value = true;

    try {
        const { data } = await http.get("/reports/revenue", {
            params: params(),
        });

        if (current === reportRequest) {
            report.value = data;
        }
    } catch (error) {
        if (current === reportRequest) {
            toast.error(
                error.response?.status === 422
                    ? "Periode atau filter tidak valid."
                    : errorMessage(error, "Gagal memuat laporan."),
            );
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
        const { data } = await http.get("/reports/revenue/transactions", {
            params: {
                ...params(),
                page: meta.page,
                search: search.value || undefined,
                per_page: 10,
            },
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

function reloadAll() {
    meta.page = 1;
    loadReport();
    loadRows();
}

function changePage(page) {
    meta.page = page;
    loadRows();
}

const points = computed(() =>
    (report.value?.series ?? []).map((row) => ({
        label: bucketLabel(row.key, report.value.period.granularity),
        title: bucketTitle(row.key, report.value.period.granularity),
        value: row.revenue,
    })),
);

watch([period, cashierId], reloadAll);
watch(
    search,
    debounce(() => {
        meta.page = 1;
        loadRows();
    }),
);

onMounted(async () => {
    try {
        const { data } = await http.get("/lookups/cashiers");
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
                <h1>Laporan Pendapatan</h1>
                <p>
                    Hanya transaksi berstatus SELESAI yang dihitung. Periode:
                    <strong>{{ describePeriod(report?.period) }}</strong>
                </p>
            </div>
            <div class="page-actions">
                <ExportButtons
                    endpoint="/reports/revenue/export"
                    :params="params()"
                />
            </div>
        </div>

        <div class="toolbar">
            <PeriodFilter v-model="period" />
            <select
                v-model="cashierId"
                class="input filter"
                aria-label="Filter kasir"
            >
                <option value="">Semua kasir</option>
                <option
                    v-for="cashier in cashiers"
                    :key="cashier.id"
                    :value="cashier.id"
                >
                    {{ cashier.name }}
                </option>
            </select>
        </div>

        <div v-if="loading && !report" class="card loading-line">
            Memuat laporan...
        </div>

        <template v-else-if="report">
            <div class="stat-grid" :style="{ opacity: loading ? 0.6 : 1 }">
                <div class="stat-card stat-card--accent">
                    <div class="stat-card__label">Total Pendapatan (kotor)</div>
                    <div class="stat-card__value">
                        {{ formatRupiah(report.summary.gross_revenue) }}
                    </div>
                </div>
                <div class="stat-card stat-card--danger">
                    <div class="stat-card__label">
                        Nilai Retur ({{
                            formatNumber(report.summary.returns_count)
                        }})
                    </div>
                    <div class="stat-card__value">
                        {{ formatRupiah(report.summary.returns_total) }}
                    </div>
                </div>
                <div class="stat-card stat-card--accent">
                    <div class="stat-card__label">Pendapatan Bersih</div>
                    <div class="stat-card__value">
                        {{ formatRupiah(report.summary.net_revenue) }}
                    </div>
                </div>
                <div class="stat-card">
                    <div class="stat-card__label">Transaksi Selesai</div>
                    <div class="stat-card__value">
                        {{ formatNumber(report.summary.transactions) }}
                    </div>
                </div>
                <div class="stat-card">
                    <div class="stat-card__label">Rata-rata Transaksi</div>
                    <div class="stat-card__value">
                        {{ formatRupiah(report.summary.average) }}
                    </div>
                </div>
                <div class="stat-card">
                    <div class="stat-card__label">Total Produk Terjual</div>
                    <div class="stat-card__value">
                        {{ formatNumber(report.summary.units_sold) }}
                    </div>
                </div>
            </div>

            <div class="card" style="margin-bottom: 20px">
                <h3>Grafik Pendapatan</h3>
                <TrendChart
                    :points="points"
                    type="bar"
                    :formatter="formatRupiah"
                />
            </div>
        </template>

        <div class="card">
            <div class="page-header" style="margin-bottom: 12px">
                <h3 style="margin: 0">Detail Transaksi</h3>
                <div class="search-box" style="min-width: 240px">
                    <span class="search-box__icon"
                        ><AppIcon name="search" :size="18"
                    /></span>
                    <input
                        v-model="search"
                        class="input"
                        type="search"
                        placeholder="Cari nomor atau nama..."
                    />
                </div>
            </div>

            <div class="table-wrap">
                <table class="table">
                    <thead>
                        <tr>
                            <th>Tanggal</th>
                            <th>No. Transaksi</th>
                            <th>Customer</th>
                            <th>Kasir</th>
                            <th class="num">Barang</th>
                            <th class="num">Total Pembayaran</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr v-if="rowsLoading && rows.length === 0">
                            <td colspan="6" class="loading-line">
                                Memuat data...
                            </td>
                        </tr>
                        <tr v-else-if="rows.length === 0">
                            <td colspan="6" class="table-empty">
                                Tidak ada transaksi selesai pada periode ini.
                            </td>
                        </tr>
                        <tr v-for="row in rows" :key="row.id">
                            <td class="nowrap">
                                {{ formatDateTime(row.completed_at) }}
                            </td>
                            <td class="nowrap">
                                <strong>{{ row.order_number }}</strong>
                            </td>
                            <td>{{ row.customer_name }}</td>
                            <td>{{ row.cashier ?? "-" }}</td>
                            <td class="num">
                                {{ formatNumber(row.total_quantity) }}
                            </td>
                            <td class="num">
                                {{ formatRupiah(row.total_amount) }}
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
        </div>
    </section>
</template>
