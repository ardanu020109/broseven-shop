<script setup>
import { onMounted, ref } from 'vue';
import http from '@/lib/http';
import AppIcon from '@/components/common/AppIcon.vue';
import OrderStatusBadge from '@/components/store/OrderStatusBadge.vue';
import { useToast } from '@/composables/useToast';
import { useAuthStore } from '@/stores/auth';
import { errorMessage, formatDateTime, formatNumber, formatRupiah } from '@/lib/utils';

const auth = useAuthStore();
const toast = useToast();

const loading = ref(true);
const stats = ref(null);
const recent = ref([]);
const activities = ref([]);

async function load() {
    loading.value = true;

    try {
        const { data } = await http.get('/kasir/dashboard');
        stats.value = data.stats;
        recent.value = data.recent_orders;
        activities.value = data.activities;
    } catch (error) {
        toast.error(errorMessage(error, 'Gagal memuat dashboard.'));
    } finally {
        loading.value = false;
    }
}

onMounted(load);
</script>

<template>
    <section>
        <div class="page-header">
            <div>
                <h1>Halo, {{ auth.user?.name }}</h1>
                <p>Ringkasan aktivitas Anda hari ini.</p>
            </div>
            <div class="page-actions">
                <button class="btn btn-outline" type="button" :disabled="loading" @click="load">
                    <AppIcon name="refresh" :size="18" /> Muat Ulang
                </button>
                <RouterLink class="btn btn-primary" to="/kasir/pos">
                    <AppIcon name="scan" :size="18" /> Buka Pelayanan
                </RouterLink>
            </div>
        </div>

        <div v-if="loading && !stats" class="card loading-line">Memuat dashboard...</div>

        <template v-else-if="stats">
            <div class="stat-grid">
                <div class="stat-card stat-card--accent">
                    <div class="stat-card__label">Pendapatan Hari Ini</div>
                    <div class="stat-card__value">{{ formatRupiah(stats.revenue_today) }}</div>
                </div>
                <div class="stat-card stat-card--accent">
                    <div class="stat-card__label">Transaksi Diproses Hari Ini</div>
                    <div class="stat-card__value">{{ formatNumber(stats.processed_today) }}</div>
                </div>
                <div class="stat-card stat-card--accent">
                    <div class="stat-card__label">Transaksi Selesai Hari Ini</div>
                    <div class="stat-card__value">{{ formatNumber(stats.completed_today) }}</div>
                </div>
                <div class="stat-card stat-card--warn">
                    <div class="stat-card__label">Menunggu Pembayaran</div>
                    <div class="stat-card__value">{{ formatNumber(stats.waiting) }}</div>
                </div>
                <div class="stat-card stat-card--warn">
                    <div class="stat-card__label">Antrean Draft</div>
                    <div class="stat-card__value">{{ formatNumber(stats.draft_queue) }}</div>
                </div>
                <div class="stat-card stat-card--danger">
                    <div class="stat-card__label">Dibatalkan Hari Ini</div>
                    <div class="stat-card__value">{{ formatNumber(stats.cancelled_today) }}</div>
                </div>
                <div class="stat-card">
                    <div class="stat-card__label">Barang Terjual Hari Ini</div>
                    <div class="stat-card__value">{{ formatNumber(stats.items_sold_today) }}</div>
                </div>
            </div>

            <div class="two-col">
                <div class="card">
                    <h3>Riwayat Transaksi Terbaru</h3>
                    <div class="table-wrap">
                        <table class="table">
                            <thead>
                                <tr>
                                    <th>No. Pesanan</th>
                                    <th>Customer</th>
                                    <th class="num">Total</th>
                                    <th>Status</th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr v-if="recent.length === 0">
                                    <td colspan="4" class="table-empty">Belum ada transaksi.</td>
                                </tr>
                                <tr v-for="item in recent" :key="item.id">
                                    <td class="nowrap"><strong>{{ item.order_number }}</strong></td>
                                    <td>{{ item.customer_name }}</td>
                                    <td class="num">{{ formatRupiah(item.total_amount) }}</td>
                                    <td><OrderStatusBadge :status="item.status" :label="item.status_label" /></td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                    <p style="margin: 12px 0 0"><RouterLink to="/kasir/riwayat">Lihat semua riwayat</RouterLink></p>
                </div>

                <div class="card">
                    <h3>Aktivitas Terakhir</h3>
                    <p v-if="activities.length === 0" class="muted" style="margin: 0">Belum ada aktivitas.</p>
                    <ul v-else class="activity-list">
                        <li v-for="activity in activities" :key="activity.id">
                            <strong>{{ activity.order_number }}</strong> · {{ activity.to_label }}
                            <small>{{ formatDateTime(activity.created_at) }}</small>
                        </li>
                    </ul>
                </div>
            </div>
        </template>
    </section>
</template>
