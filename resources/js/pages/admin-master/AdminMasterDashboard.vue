<script setup>
import { onMounted, ref } from 'vue';
import http from '@/lib/http';
import AppIcon from '@/components/common/AppIcon.vue';
import StatusBadge from '@/components/common/StatusBadge.vue';
import { useToast } from '@/composables/useToast';
import { useAuthStore } from '@/stores/auth';
import { errorMessage, formatDateTime, formatNumber, formatRupiah } from '@/lib/utils';

const auth = useAuthStore();
const toast = useToast();

const loading = ref(true);
const data = ref(null);

async function load() {
    loading.value = true;

    try {
        const response = await http.get('/admin-master/dashboard');
        data.value = response.data;
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
                <p>Ringkasan data barang dan inventori.</p>
            </div>
            <div class="page-actions">
                <button class="btn btn-outline" type="button" :disabled="loading" @click="load">
                    <AppIcon name="refresh" :size="18" /> Muat Ulang
                </button>
                <RouterLink class="btn btn-primary" to="/admin-master/products/create">
                    <AppIcon name="plus" :size="18" /> Tambah Barang
                </RouterLink>
            </div>
        </div>

        <div v-if="loading && !data" class="card loading-line">Memuat dashboard...</div>

        <template v-else-if="data">
            <div class="stat-grid">
                <div class="stat-card stat-card--accent">
                    <div class="stat-card__label">Total Produk</div>
                    <div class="stat-card__value">{{ formatNumber(data.totals.products) }}</div>
                    <div class="hint">{{ formatNumber(data.stock.inactive) }} tidak aktif</div>
                </div>
                <div class="stat-card">
                    <div class="stat-card__label">Total Kategori</div>
                    <div class="stat-card__value">{{ formatNumber(data.totals.categories) }}</div>
                    <div class="hint">{{ formatNumber(data.totals.categories_active) }} aktif</div>
                </div>
                <div class="stat-card">
                    <div class="stat-card__label">Total Merek</div>
                    <div class="stat-card__value">{{ formatNumber(data.totals.brands) }}</div>
                    <div class="hint">{{ formatNumber(data.totals.brands_active) }} aktif</div>
                </div>
                <div class="stat-card">
                    <div class="stat-card__label">Total Tipe Barang</div>
                    <div class="stat-card__value">{{ formatNumber(data.totals.product_types) }}</div>
                    <div class="hint">{{ formatNumber(data.totals.product_types_active) }} aktif</div>
                </div>
                <div class="stat-card stat-card--accent">
                    <div class="stat-card__label">Produk Tersedia</div>
                    <div class="stat-card__value">{{ formatNumber(data.stock.available) }}</div>
                </div>
                <div class="stat-card stat-card--warn">
                    <div class="stat-card__label">Produk Hampir Habis</div>
                    <div class="stat-card__value">{{ formatNumber(data.stock.low) }}</div>
                </div>
                <div class="stat-card stat-card--danger">
                    <div class="stat-card__label">Produk Habis</div>
                    <div class="stat-card__value">{{ formatNumber(data.stock.out) }}</div>
                </div>
            </div>

            <div class="two-col">
                <div class="card">
                    <h3>Produk Terbaru</h3>
                    <div class="table-wrap">
                        <table class="table">
                            <thead>
                                <tr>
                                    <th>Barang</th>
                                    <th class="num">Harga Jual</th>
                                    <th class="num">Stok</th>
                                    <th>Status</th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr v-if="data.latest_products.length === 0">
                                    <td colspan="4" class="table-empty">Belum ada produk.</td>
                                </tr>
                                <tr v-for="item in data.latest_products" :key="item.id">
                                    <td>
                                        <div class="product-cell">
                                            <span class="thumb">
                                                <img v-if="item.image_url" :src="item.image_url" :alt="item.name" loading="lazy" />
                                                <AppIcon v-else name="image" />
                                            </span>
                                            <div>
                                                <RouterLink :to="`/admin-master/products/${item.id}`"><strong>{{ item.name }}</strong></RouterLink>
                                                <div class="hint">{{ item.code }} · {{ item.category ?? 'Tanpa kategori' }}</div>
                                            </div>
                                        </div>
                                    </td>
                                    <td class="num">{{ formatRupiah(item.selling_price) }}</td>
                                    <td class="num">{{ formatNumber(item.stock) }}</td>
                                    <td>
                                        <span v-if="!item.is_active" class="badge badge-muted">Nonaktif</span>
                                        <StatusBadge v-else kind="stock" :value="item.stock_status" />
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                    <p style="margin: 12px 0 0"><RouterLink to="/admin-master/products">Lihat semua barang</RouterLink></p>
                </div>

                <div class="card">
                    <h3>Aktivitas Perubahan Data Barang</h3>
                    <p v-if="data.activities.length === 0" class="muted" style="margin: 0">Belum ada aktivitas.</p>
                    <ul v-else class="activity-list">
                        <li v-for="item in data.activities" :key="item.id">
                            {{ item.description }}
                            <small>{{ item.user ?? 'Sistem' }} · {{ formatDateTime(item.created_at) }}</small>
                        </li>
                    </ul>
                </div>
            </div>
        </template>
    </section>
</template>
