<script setup>
import { computed, onMounted, ref, watch } from 'vue';
import http from '@/lib/http';
import AppIcon from '@/components/common/AppIcon.vue';
import TrendChart from '@/components/charts/TrendChart.vue';
import PeriodFilter from '@/components/common/PeriodFilter.vue';
import OrderStatusBadge from '@/components/store/OrderStatusBadge.vue';
import { useToast } from '@/composables/useToast';
import { bucketLabel, bucketTitle, defaultPeriod, describePeriod, periodParams } from '@/lib/period';
import { errorMessage, formatDateTime, formatNumber, formatRupiah } from '@/lib/utils';

const toast = useToast();

const period = ref(defaultPeriod('month'));
const data = ref(null);
const loading = ref(true);
let requestId = 0;

async function load() {
    const current = ++requestId;
    loading.value = true;

    try {
        const response = await http.get('/superadmin/dashboard', { params: periodParams(period.value) });

        if (current === requestId) {
            data.value = response.data;
        }
    } catch (error) {
        if (current === requestId) {
            toast.error(error.response?.status === 422 ? 'Periode tidak valid.' : errorMessage(error, 'Gagal memuat dashboard.'));
        }
    } finally {
        if (current === requestId) {
            loading.value = false;
        }
    }
}

function points(field) {
    if (!data.value) {
        return [];
    }

    const granularity = data.value.period.granularity;

    return data.value.series.map((row) => ({
        label: bucketLabel(row.key, granularity),
        title: bucketTitle(row.key, granularity),
        value: row[field],
    }));
}

const revenuePoints = computed(() => points('revenue'));
const transactionPoints = computed(() => points('transactions'));
const maxQuantity = computed(() => Math.max(...(data.value?.top_products.map((item) => item.quantity) ?? [1]), 1));

watch(period, load);
onMounted(load);
</script>

<template>
    <section>
        <div class="page-header">
            <div>
                <h1>Dashboard</h1>
                <p>Ringkasan seluruh aktivitas toko.</p>
            </div>
            <div class="page-actions">
                <button class="btn btn-outline" type="button" :disabled="loading" @click="load">
                    <AppIcon name="refresh" :size="18" /> Muat Ulang
                </button>
            </div>
        </div>

        <div v-if="loading && !data" class="card loading-line">Memuat dashboard...</div>

        <template v-else-if="data">
            <div class="stat-grid">
                <div class="stat-card stat-card--accent">
                    <div class="stat-card__label">Pendapatan Hari Ini</div>
                    <div class="stat-card__value">{{ formatRupiah(data.totals.revenue_today) }}</div>
                </div>
                <div class="stat-card stat-card--accent">
                    <div class="stat-card__label">Pendapatan Bulan Ini</div>
                    <div class="stat-card__value">{{ formatRupiah(data.totals.revenue_month) }}</div>
                </div>
                <div class="stat-card stat-card--accent">
                    <div class="stat-card__label">Pendapatan Tahun Ini</div>
                    <div class="stat-card__value">{{ formatRupiah(data.totals.revenue_year) }}</div>
                </div>
                <div class="stat-card stat-card--accent">
                    <div class="stat-card__label">Transaksi Selesai Hari Ini</div>
                    <div class="stat-card__value">{{ formatNumber(data.totals.transactions_today) }}</div>
                </div>
                <div class="stat-card">
                    <div class="stat-card__label">Jumlah Produk</div>
                    <div class="stat-card__value">{{ formatNumber(data.products.total) }}</div>
                </div>
                <div class="stat-card stat-card--accent">
                    <div class="stat-card__label">Produk Tersedia</div>
                    <div class="stat-card__value">{{ formatNumber(data.products.available) }}</div>
                </div>
                <div class="stat-card stat-card--warn">
                    <div class="stat-card__label">Stok Menipis</div>
                    <div class="stat-card__value">{{ formatNumber(data.products.low) }}</div>
                </div>
                <div class="stat-card stat-card--danger">
                    <div class="stat-card__label">Produk Habis</div>
                    <div class="stat-card__value">{{ formatNumber(data.products.out) }}</div>
                </div>
                <div class="stat-card">
                    <div class="stat-card__label">Total Customer</div>
                    <div class="stat-card__value">{{ formatNumber(data.totals.customers) }}</div>
                </div>
                <div class="stat-card">
                    <div class="stat-card__label">Total Kasir</div>
                    <div class="stat-card__value">{{ formatNumber(data.totals.cashiers) }}</div>
                    <div class="hint">{{ formatNumber(data.totals.cashiers_active) }} aktif</div>
                </div>
            </div>

            <div class="page-header">
                <div>
                    <h2 style="margin: 0">Periode: {{ describePeriod(data.period) }}</h2>
                    <p>Pendapatan kotor {{ formatRupiah(data.period_summary.gross_revenue) }} · retur {{ formatRupiah(data.period_summary.returns_total) }} · bersih {{ formatRupiah(data.period_summary.net_revenue) }} · {{ formatNumber(data.period_summary.transactions) }} transaksi</p>
                </div>
                <PeriodFilter v-model="period" />
            </div>

            <div class="chart-grid" :style="{ opacity: loading ? 0.6 : 1 }">
                <div class="card">
                    <h3>Grafik Pendapatan</h3>
                    <TrendChart :points="revenuePoints" type="bar" :formatter="formatRupiah" />
                </div>
                <div class="card">
                    <h3>Grafik Transaksi</h3>
                    <TrendChart :points="transactionPoints" type="line" color="var(--dark)" :formatter="formatNumber" />
                </div>
            </div>

            <div class="two-col">
                <div class="card">
                    <h3>Produk Terlaris</h3>
                    <p v-if="data.top_products.length === 0" class="muted" style="margin: 0">Belum ada penjualan pada periode ini.</p>
                    <ol v-else class="rank">
                        <li v-for="(item, index) in data.top_products" :key="item.product_id">
                            <span class="rank__no">{{ index + 1 }}</span>
                            <div>
                                <strong>{{ item.name }}</strong>
                                <div class="rank__bar" :style="{ width: `${(item.quantity / maxQuantity) * 100}%` }"></div>
                            </div>
                            <span><strong>{{ formatNumber(item.quantity) }}</strong> <span class="muted">terjual</span></span>
                        </li>
                    </ol>
                </div>

                <div class="card">
                    <h3>Aktivitas Kasir Terbaru</h3>
                    <p v-if="data.activities.length === 0" class="muted" style="margin: 0">Belum ada aktivitas kasir.</p>
                    <ul v-else class="activity-list">
                        <li v-for="item in data.activities" :key="item.id">
                            <strong>{{ item.cashier }}</strong> · {{ item.to_label }} <span class="muted">{{ item.order_number }}</span>
                            <small>{{ formatDateTime(item.created_at) }}</small>
                        </li>
                    </ul>
                </div>
            </div>

            <div class="card" style="margin-top: 20px">
                <h3>Transaksi Terbaru</h3>
                <div class="table-wrap">
                    <table class="table">
                        <thead>
                            <tr>
                                <th>No. Pesanan</th>
                                <th>Customer</th>
                                <th>Produk</th>
                                <th class="num">Total</th>
                                <th>Status</th>
                                <th>Kasir</th>
                                <th>Waktu</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr v-if="data.recent_orders.length === 0">
                                <td colspan="7" class="table-empty">Belum ada transaksi.</td>
                            </tr>
                            <tr v-for="order in data.recent_orders" :key="order.id">
                                <td class="nowrap"><strong>{{ order.order_number }}</strong></td>
                                <td>{{ order.customer_name }}</td>
                                <td class="muted">{{ order.items_summary }}</td>
                                <td class="num">{{ formatRupiah(order.total_amount) }}</td>
                                <td><OrderStatusBadge :status="order.status" :label="order.status_label" /></td>
                                <td>{{ order.cashier ?? '-' }}</td>
                                <td class="nowrap">{{ formatDateTime(order.created_at) }}</td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>
        </template>
    </section>
</template>
