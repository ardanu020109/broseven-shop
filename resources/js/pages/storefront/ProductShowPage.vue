<script setup>
import { computed, ref, watch } from 'vue';
import { useRoute } from 'vue-router';
import http from '@/lib/http';
import AppIcon from '@/components/common/AppIcon.vue';
import QtyStepper from '@/components/store/QtyStepper.vue';
import { useToast } from '@/composables/useToast';
import { useCartStore } from '@/stores/cart';
import { errorMessage, formatNumber, formatRupiah } from '@/lib/utils';

const route = useRoute();
const cart = useCartStore();
const toast = useToast();

const product = ref(null);
const loading = ref(true);
const notFound = ref(false);
const quantity = ref(1);

const productId = computed(() => Number(route.params.id));
const inCart = computed(() => (product.value ? cart.quantityOf(product.value.id) : 0));
const remaining = computed(() => Math.max(0, (product.value?.stock ?? 0) - inCart.value));

async function load() {
    loading.value = true;
    notFound.value = false;
    quantity.value = 1;

    try {
        const { data } = await http.get(`/store/products/${productId.value}`);
        product.value = data.data;
    } catch (error) {
        product.value = null;

        if (error.response?.status === 404) {
            notFound.value = true;
        } else {
            toast.error(errorMessage(error, 'Gagal memuat produk.'));
        }
    } finally {
        loading.value = false;
    }
}

watch(productId, load, { immediate: true });

watch(remaining, (value) => {
    if (quantity.value > value) {
        quantity.value = Math.max(1, value);
    }
});

function addToCart() {
    const result = cart.add(product.value, quantity.value);

    if (result.ok) {
        toast.success(`${product.value.name} ditambahkan ke keranjang.`);
        quantity.value = 1;
    } else {
        toast.error(result.message);
    }
}
</script>

<template>
    <div class="container">
        <div v-if="loading" class="card loading-line">Memuat produk...</div>

        <div v-else-if="notFound || !product" class="card empty-state">
            <h3>Produk tidak ditemukan</h3>
            <p>Produk mungkin sudah tidak dijual.</p>
            <RouterLink class="btn btn-primary" :to="{ name: 'store.products' }">Lihat Semua Produk</RouterLink>
        </div>

        <div v-else class="product-show">
            <div class="product-show__image">
                <img v-if="product.image_url" :src="product.image_url" :alt="product.name" />
                <AppIcon v-else name="image" :size="64" />
            </div>

            <div>
                <RouterLink :to="{ name: 'store.products' }" class="muted">
                    <AppIcon name="arrow-left" :size="16" /> Kembali ke produk
                </RouterLink>

                <h1 style="margin-top: 12px">{{ product.name }}</h1>
                <p class="muted" style="margin: 0">
                    {{ product.category ?? 'Tanpa kategori' }}<span v-if="product.brand"> · {{ product.brand }}</span>
                </p>

                <p class="product-show__price">{{ formatRupiah(product.price) }}</p>

                <p>
                    <span v-if="product.in_stock" class="badge badge-success">Stok tersedia: {{ formatNumber(product.stock) }} {{ product.unit ?? '' }}</span>
                    <span v-else class="badge badge-danger">Stok habis</span>
                </p>

                <p style="white-space: pre-line">{{ product.description || 'Belum ada deskripsi untuk produk ini.' }}</p>

                <div class="product-show__buy">
                    <QtyStepper v-model="quantity" :min="1" :max="Math.max(1, remaining)" :disabled="remaining <= 0" />
                    <button class="btn btn-primary" type="button" :disabled="remaining <= 0" @click="addToCart">
                        <AppIcon name="cart" :size="18" /> Tambah ke Keranjang
                    </button>
                </div>

                <p v-if="product.in_stock && inCart > 0" class="hint" style="margin-top: 10px">
                    Di keranjang: {{ inCart }}.
                    <span v-if="remaining <= 0">Jumlah sudah mencapai stok tersedia.</span>
                </p>
            </div>
        </div>
    </div>
</template>
