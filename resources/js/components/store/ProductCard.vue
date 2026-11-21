<script setup>
import AppIcon from '@/components/common/AppIcon.vue';
import { useToast } from '@/composables/useToast';
import { useCartStore } from '@/stores/cart';
import { formatNumber, formatRupiah } from '@/lib/utils';

const props = defineProps({
    product: { type: Object, required: true },
});

const cart = useCartStore();
const toast = useToast();

function addToCart() {
    const result = cart.add(props.product, 1);

    if (result.ok) {
        toast.success(`${props.product.name} ditambahkan ke keranjang.`);
    } else {
        toast.error(result.message);
    }
}
</script>

<template>
    <article class="pcard">
        <RouterLink :to="{ name: 'store.product', params: { id: product.id } }" class="pcard__media">
            <img v-if="product.image_url" :src="product.image_url" :alt="product.name" loading="lazy" />
            <span v-else class="pcard__placeholder"><AppIcon name="image" :size="40" /></span>
            <span v-if="!product.in_stock" class="badge badge-danger pcard__flag">Stok habis</span>
        </RouterLink>

        <div class="pcard__body">
            <span class="pcard__cat">{{ product.category ?? 'Tanpa kategori' }}</span>
            <h3 class="pcard__name">{{ product.name }}</h3>
            <strong class="pcard__price">{{ formatRupiah(product.price) }}</strong>
            <span class="pcard__stock">
                {{ product.in_stock ? `Stok: ${formatNumber(product.stock)} ${product.unit ?? ''}` : 'Stok habis' }}
            </span>

            <div class="pcard__actions">
                <button class="btn btn-primary" type="button" :disabled="!product.in_stock" @click="addToCart">
                    <AppIcon name="cart" :size="16" /> Tambah ke Keranjang
                </button>
                <RouterLink class="btn btn-outline" :to="{ name: 'store.product', params: { id: product.id } }">
                    Detail
                </RouterLink>
            </div>
        </div>
    </article>
</template>
