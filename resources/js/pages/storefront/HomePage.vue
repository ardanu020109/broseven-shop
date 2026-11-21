<script setup>
import { onMounted, ref } from 'vue';
import http from '@/lib/http';
import ProductCard from '@/components/store/ProductCard.vue';
import { useBranding } from '@/composables/useBranding';

const branding = useBranding();

const categories = ref([]);
const latest = ref([]);
const popular = ref([]);
const loading = ref(true);

onMounted(async () => {
    const [filters, latestResponse, popularResponse] = await Promise.allSettled([
        http.get('/store/filters'),
        http.get('/store/products', { params: { sort: 'latest', per_page: 8 } }),
        http.get('/store/products', { params: { sort: 'popular', only_sold: 1, per_page: 8 } }),
    ]);

    categories.value = filters.status === 'fulfilled' ? filters.value.data.categories : [];
    latest.value = latestResponse.status === 'fulfilled' ? latestResponse.value.data.data : [];
    popular.value = popularResponse.status === 'fulfilled' ? popularResponse.value.data.data : [];
    loading.value = false;
});
</script>

<template>
    <div class="container">
        <section class="hero">
            <div>
                <h1>Belanja mudah di {{ branding.company.store_name }}</h1>
                <p>
                    {{ branding.company.description || 'Pilih produk, buat pesanan, lalu tunjukkan QR Code ke kasir dan bayar langsung di toko.' }}
                </p>
                <div class="hero__actions">
                    <RouterLink class="btn btn-primary" :to="{ name: 'store.products' }">Mulai Belanja</RouterLink>
                    <RouterLink class="btn btn-outline" :to="{ name: 'store.orders' }">Lihat Pesanan</RouterLink>
                </div>
            </div>

            <svg class="hero__art" viewBox="0 0 320 260" aria-hidden="true">
                <rect x="40" y="70" width="240" height="160" rx="24" fill="var(--primary)" opacity="0.18" />
                <rect x="70" y="100" width="180" height="130" rx="18" fill="var(--white)" stroke="var(--dark)" stroke-width="4" />
                <path d="M120 100v-18a40 40 0 0 1 80 0v18" fill="none" stroke="var(--dark)" stroke-width="6" stroke-linecap="round" />
                <circle cx="160" cy="165" r="26" fill="var(--secondary)" />
                <path d="M148 165l9 9 17-18" fill="none" stroke="var(--dark)" stroke-width="6" stroke-linecap="round" stroke-linejoin="round" />
                <circle cx="58" cy="60" r="14" fill="var(--secondary)" />
                <circle cx="270" cy="48" r="9" fill="var(--primary)" />
            </svg>
        </section>

        <div class="steps-info">
            <div class="steps-info__item">
                <span class="steps-info__num">1</span>
                <div><strong>Pilih &amp; pesan</strong><p>Masukkan produk ke keranjang lalu buat pesanan.</p></div>
            </div>
            <div class="steps-info__item">
                <span class="steps-info__num">2</span>
                <div><strong>Tunjukkan QR</strong><p>Datang ke kasir dan tunjukkan QR Code pesanan Anda.</p></div>
            </div>
            <div class="steps-info__item">
                <span class="steps-info__num">3</span>
                <div><strong>Bayar di kasir</strong><p>Pembayaran dilakukan langsung di toko.</p></div>
            </div>
        </div>

        <section v-if="categories.length" class="section">
            <div class="section__head"><h2>Kategori</h2></div>
            <div class="chips">
                <RouterLink
                    v-for="category in categories"
                    :key="category.id"
                    class="chip"
                    :to="{ name: 'store.products', query: { kategori: category.id } }"
                >
                    {{ category.name }}
                </RouterLink>
            </div>
        </section>

        <section class="section">
            <div class="section__head">
                <h2>Produk Terbaru</h2>
                <RouterLink :to="{ name: 'store.products' }">Lihat semua</RouterLink>
            </div>
            <div v-if="loading" class="card loading-line">Memuat produk...</div>
            <div v-else-if="latest.length === 0" class="card empty-state">
                <p>Belum ada produk yang tersedia.</p>
            </div>
            <div v-else class="product-grid">
                <ProductCard v-for="product in latest" :key="product.id" :product="product" />
            </div>
        </section>

        <section v-if="popular.length" class="section">
            <div class="section__head">
                <h2>Produk Populer</h2>
                <RouterLink :to="{ name: 'store.products', query: { urut: 'popular' } }">Lihat semua</RouterLink>
            </div>
            <div class="product-grid">
                <ProductCard v-for="product in popular" :key="product.id" :product="product" />
            </div>
        </section>
    </div>
</template>
