<script setup>
import { computed, onMounted, reactive, ref, watch } from 'vue';
import { useRoute, useRouter } from 'vue-router';
import http from '@/lib/http';
import AppIcon from '@/components/common/AppIcon.vue';
import AppPagination from '@/components/common/AppPagination.vue';
import StatusBadge from '@/components/common/StatusBadge.vue';
import StockAdjustModal from '@/components/products/StockAdjustModal.vue';
import { useToast } from '@/composables/useToast';
import { useAuthStore } from '@/stores/auth';
import { errorMessage, formatDateTime, formatNumber, formatRupiah } from '@/lib/utils';

const route = useRoute();
const router = useRouter();
const auth = useAuthStore();
const toast = useToast();

const base = computed(() => (auth.role === 'superadmin' ? '/superadmin' : '/admin-master'));
const canManage = computed(() => auth.role === 'admin_master');
const productId = computed(() => Number(route.params.id));

const product = ref(null);
const loading = ref(true);
const movements = ref([]);
const movementsLoading = ref(false);
const meta = reactive({ page: 1, lastPage: 1, total: 0, from: 0, to: 0 });
const typeFilter = ref('');
const adjustOpen = ref(false);

const typeOptions = [
    { value: 'STOCK_IN', label: 'Stok Masuk' },
    { value: 'SALE', label: 'Penjualan' },
    { value: 'SALE_CANCELLED', label: 'Pembatalan Penjualan' },
    { value: 'RETURN', label: 'Retur' },
    { value: 'ADJUSTMENT', label: 'Penyesuaian' },
];

const typeBadge = {
    STOCK_IN: 'badge-success',
    RETURN: 'badge-success',
    SALE_CANCELLED: 'badge-success',
    SALE: 'badge-info',
    ADJUSTMENT: 'badge-muted',
};

async function loadProduct() {
    loading.value = true;

    try {
        const { data } = await http.get(`/products/${productId.value}`);
        product.value = data.data;
    } catch (error) {
        toast.error(errorMessage(error, 'Barang tidak ditemukan.'));
        router.replace(`${base.value}/products`);
    } finally {
        loading.value = false;
    }
}

async function loadMovements() {
    movementsLoading.value = true;

    try {
        const { data } = await http.get(`/products/${productId.value}/stock-movements`, {
            params: { page: meta.page, type: typeFilter.value || undefined },
        });

        movements.value = data.data;
        meta.page = data.current_page;
        meta.lastPage = data.last_page;
        meta.total = data.total;
        meta.from = data.from ?? 0;
        meta.to = data.to ?? 0;
    } catch (error) {
        toast.error(errorMessage(error, 'Gagal memuat riwayat stok.'));
    } finally {
        movementsLoading.value = false;
    }
}

function changePage(page) {
    meta.page = page;
    loadMovements();
}

watch(typeFilter, () => {
    meta.page = 1;
    loadMovements();
});

watch(productId, () => {
    meta.page = 1;
    typeFilter.value = '';
    loadProduct();
    loadMovements();
});

onMounted(() => {
    loadProduct();
    loadMovements();
});

async function onStockSaved() {
    meta.page = 1;
    await Promise.all([loadProduct(), loadMovements()]);
}
</script>

<template>
    <section>
        <div class="page-header">
            <div>
                <h1>Detail Barang</h1>
                <p v-if="product">{{ product.code }}</p>
            </div>
            <div class="page-actions">
                <button class="btn btn-outline" type="button" @click="router.push(`${base}/products`)">
                    <AppIcon name="arrow-left" :size="18" /> Kembali
                </button>
                <template v-if="canManage && product">
                    <button class="btn btn-secondary" type="button" @click="adjustOpen = true">
                        <AppIcon name="layers" :size="18" /> Sesuaikan Stok
                    </button>
                    <button class="btn btn-primary" type="button" @click="router.push(`${base}/products/${product.id}/edit`)">
                        <AppIcon name="edit" :size="18" /> Edit
                    </button>
                </template>
            </div>
        </div>

        <div v-if="loading && !product" class="card loading-line">Memuat data...</div>

        <template v-else-if="product">
            <div class="detail-layout">
                <div class="card">
                    <div class="detail-image">
                        <img v-if="product.image_url" :src="product.image_url" :alt="product.name" />
                        <AppIcon v-else name="image" :size="48" />
                    </div>
                </div>

                <div class="card">
                    <h2 style="margin-bottom: 10px">{{ product.name }}</h2>
                    <div style="display: flex; flex-wrap: wrap; gap: 8px; margin-bottom: 20px">
                        <StatusBadge kind="stock" :value="product.stock_status" />
                        <StatusBadge :value="product.is_active" />
                    </div>

                    <dl class="detail-grid">
                        <div><dt>Barcode</dt><dd>{{ product.barcode || '-' }}</dd></div>
                        <div><dt>Kategori</dt><dd>{{ product.category?.name ?? '-' }}</dd></div>
                        <div><dt>Tipe Barang</dt><dd>{{ product.product_type?.name ?? '-' }}</dd></div>
                        <div><dt>Merek</dt><dd>{{ product.brand?.name ?? '-' }}</dd></div>
                        <div><dt>Satuan</dt><dd>{{ product.unit?.name ?? '-' }}</dd></div>
                        <div><dt>Harga Jual</dt><dd>{{ formatRupiah(product.selling_price) }}</dd></div>
                        <div v-if="'purchase_price' in product"><dt>Harga Beli</dt><dd>{{ formatRupiah(product.purchase_price) }}</dd></div>
                        <div><dt>Stok</dt><dd>{{ formatNumber(product.stock) }} {{ product.unit?.name ?? '' }}</dd></div>
                        <div><dt>Stok Minimum</dt><dd>{{ formatNumber(product.min_stock) }}</dd></div>
                        <div><dt>Dibuat</dt><dd>{{ formatDateTime(product.created_at) }}<span v-if="product.creator" class="muted"> oleh {{ product.creator }}</span></dd></div>
                        <div><dt>Terakhir Diubah</dt><dd>{{ formatDateTime(product.updated_at) }}<span v-if="product.updater" class="muted"> oleh {{ product.updater }}</span></dd></div>
                        <div style="grid-column: 1 / -1"><dt>Deskripsi</dt><dd style="font-weight: 400; white-space: pre-line">{{ product.description || '-' }}</dd></div>
                    </dl>
                </div>
            </div>

            <div class="card" style="margin-top: 20px">
                <div class="page-header" style="margin-bottom: 14px">
                    <h3 style="margin: 0"><AppIcon name="clock" :size="18" /> Riwayat Perubahan Stok</h3>
                    <select v-model="typeFilter" class="input" style="width: 220px" aria-label="Filter jenis">
                        <option value="">Semua jenis</option>
                        <option v-for="option in typeOptions" :key="option.value" :value="option.value">{{ option.label }}</option>
                    </select>
                </div>

                <div class="table-wrap">
                    <table class="table">
                        <thead>
                            <tr>
                                <th>Waktu</th>
                                <th>Jenis</th>
                                <th class="num">Perubahan</th>
                                <th class="num">Sebelum</th>
                                <th class="num">Sesudah</th>
                                <th>Keterangan</th>
                                <th>Oleh</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr v-if="movementsLoading && movements.length === 0">
                                <td colspan="7" class="loading-line">Memuat riwayat...</td>
                            </tr>
                            <tr v-else-if="movements.length === 0">
                                <td colspan="7" class="table-empty">Belum ada riwayat stok.</td>
                            </tr>
                            <tr v-for="movement in movements" :key="movement.id">
                                <td class="nowrap">{{ formatDateTime(movement.created_at) }}</td>
                                <td><span class="badge" :class="typeBadge[movement.type] ?? 'badge-muted'">{{ movement.type_label }}</span></td>
                                <td class="num" :class="movement.quantity_change > 0 ? 'change-plus' : 'change-minus'">
                                    {{ movement.quantity_change > 0 ? '+' : '' }}{{ formatNumber(movement.quantity_change) }}
                                </td>
                                <td class="num">{{ formatNumber(movement.stock_before) }}</td>
                                <td class="num">{{ formatNumber(movement.stock_after) }}</td>
                                <td>{{ movement.note || '-' }}</td>
                                <td class="nowrap">{{ movement.user ?? '-' }}</td>
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

            <StockAdjustModal v-if="canManage" v-model="adjustOpen" :product="product" @saved="onStockSaved" />
        </template>
    </section>
</template>
