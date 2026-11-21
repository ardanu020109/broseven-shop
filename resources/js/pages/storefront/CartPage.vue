<script setup>
import { onMounted } from 'vue';
import { useRouter } from 'vue-router';
import AppIcon from '@/components/common/AppIcon.vue';
import QtyStepper from '@/components/store/QtyStepper.vue';
import { useCartStore } from '@/stores/cart';
import { formatNumber, formatRupiah } from '@/lib/utils';

const router = useRouter();
const cart = useCartStore();

onMounted(() => cart.refresh());
</script>

<template>
    <div class="container">
        <div class="page-title">
            <h1>Keranjang Belanja</h1>
            <p>Periksa kembali barang Anda sebelum checkout.</p>
        </div>

        <div v-if="!cart.ready" class="card loading-line">Memuat keranjang...</div>

        <div v-else-if="cart.error && cart.lines.length === 0" class="card empty-state">
            <h3>Keranjang gagal dimuat</h3>
            <p>Periksa koneksi Anda lalu coba lagi.</p>
            <button class="btn btn-primary" type="button" @click="cart.refresh()">
                <AppIcon name="refresh" :size="18" /> Coba Lagi
            </button>
        </div>

        <div v-else-if="cart.isEmpty" class="card empty-state">
            <h3>Keranjang masih kosong</h3>
            <p>Yuk pilih produk yang Anda butuhkan.</p>
            <RouterLink class="btn btn-primary" :to="{ name: 'store.products' }">Mulai Belanja</RouterLink>
        </div>

        <div v-else class="cart-layout">
            <div class="cart-list">
                <article v-for="line in cart.lines" :key="line.product_id" class="cart-line card">
                    <div class="cart-line__img">
                        <img v-if="line.image_url" :src="line.image_url" :alt="line.name" />
                        <AppIcon v-else name="image" :size="28" />
                    </div>

                    <div>
                        <h3 class="cart-line__name">
                            <RouterLink :to="{ name: 'store.product', params: { id: line.product_id } }">{{ line.name }}</RouterLink>
                        </h3>
                        <span class="muted">{{ formatRupiah(line.unit_price) }}<span v-if="line.unit"> / {{ line.unit }}</span></span>
                        <div class="hint">Stok tersedia: {{ formatNumber(line.stock) }}</div>
                    </div>

                    <div class="cart-line__controls">
                        <QtyStepper
                            :model-value="line.quantity"
                            :min="1"
                            :max="line.stock"
                            @update:model-value="cart.setQuantity(line.product_id, $event)"
                        />
                        <strong class="cart-line__sub">{{ formatRupiah(line.unit_price * line.quantity) }}</strong>
                        <button class="btn btn-outline-danger btn-sm" type="button" @click="cart.remove(line.product_id)">
                            <AppIcon name="trash" :size="16" /> Hapus
                        </button>
                    </div>
                </article>
            </div>

            <aside class="card summary">
                <h3>Ringkasan</h3>
                <div class="summary__row">
                    <span>Jumlah barang</span>
                    <strong>{{ formatNumber(cart.count) }}</strong>
                </div>
                <div class="summary__row summary__row--total">
                    <span>Total belanja</span>
                    <span>{{ formatRupiah(cart.total) }}</span>
                </div>
                <p class="hint">Total akhir dihitung ulang oleh sistem saat pesanan dibuat.</p>
                <button class="btn btn-primary btn-block" type="button" :disabled="cart.loading" @click="router.push({ name: 'store.checkout' })">
                    Lanjut ke Checkout
                </button>
                <RouterLink class="btn btn-outline btn-block" style="margin-top: 10px" :to="{ name: 'store.products' }">
                    Tambah Barang Lain
                </RouterLink>
            </aside>
        </div>
    </div>
</template>
