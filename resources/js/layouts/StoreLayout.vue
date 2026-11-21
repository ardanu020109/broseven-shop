<script setup>
import { computed, onBeforeUnmount, onMounted, ref, watch } from 'vue';
import { useRoute } from 'vue-router';
import http from '@/lib/http';
import AppIcon from '@/components/common/AppIcon.vue';
import { useBranding } from '@/composables/useBranding';
import { useCartStore } from '@/stores/cart';

const route = useRoute();
const branding = useBranding();
const cart = useCartStore();

const company = computed(() => branding.company);
const menuOpen = ref(false);
const categoriesOpen = ref(false);
const categories = ref([]);
const dropdownEl = ref(null);

function onOutside(event) {
    if (categoriesOpen.value && dropdownEl.value && !dropdownEl.value.contains(event.target)) {
        categoriesOpen.value = false;
    }
}

onMounted(async () => {
    document.addEventListener('mousedown', onOutside);
    cart.init();

    try {
        const { data } = await http.get('/store/filters');
        categories.value = data.categories;
    } catch {
        categories.value = [];
    }
});

onBeforeUnmount(() => document.removeEventListener('mousedown', onOutside));

watch(
    () => route.fullPath,
    () => {
        menuOpen.value = false;
        categoriesOpen.value = false;
    },
);
</script>

<template>
    <div class="store">
        <header class="nav no-print">
            <div class="container nav__inner">
                <RouterLink :to="{ name: 'store.home' }" class="brand">
                    <span class="brand__logo">
                        <img v-if="company.logo_url" :src="company.logo_url" :alt="company.store_name" />
                        <template v-else>B7</template>
                    </span>
                    <span class="brand__name">{{ company.store_name }}</span>
                </RouterLink>

                <nav class="nav__links" :class="{ 'nav__links--open': menuOpen }" aria-label="Navigasi utama">
                    <RouterLink :to="{ name: 'store.home' }" class="nav__link">Beranda</RouterLink>
                    <RouterLink :to="{ name: 'store.products' }" class="nav__link">Semua Produk</RouterLink>

                    <div ref="dropdownEl" class="nav__dropdown">
                        <button
                            type="button"
                            class="nav__link"
                            :aria-expanded="categoriesOpen"
                            @click="categoriesOpen = !categoriesOpen"
                        >
                            Kategori <AppIcon name="chevron-down" :size="16" />
                        </button>
                        <div v-if="categoriesOpen" class="nav__menu">
                            <RouterLink
                                v-for="category in categories"
                                :key="category.id"
                                class="nav__link"
                                :to="{ name: 'store.products', query: { kategori: category.id } }"
                            >
                                {{ category.name }}
                            </RouterLink>
                            <span v-if="categories.length === 0" class="nav__empty">Belum ada kategori.</span>
                        </div>
                    </div>

                    <RouterLink :to="{ name: 'store.about' }" class="nav__link">Tentang Kami</RouterLink>
                    <RouterLink :to="{ name: 'store.orders' }" class="nav__link nav__orders">
                        <AppIcon name="receipt" :size="18" /> Lihat Pesanan
                    </RouterLink>
                </nav>

                <span class="nav__spacer"></span>

                <RouterLink :to="{ name: 'store.cart' }" class="btn btn-primary nav__cart" aria-label="Keranjang">
                    <AppIcon name="cart" :size="18" />
                    <span class="nav__cart-label">Keranjang</span>
                    <span class="nav__cart-count">{{ cart.count }}</span>
                </RouterLink>

                <button
                    class="nav__toggle"
                    type="button"
                    :aria-label="menuOpen ? 'Tutup menu' : 'Buka menu'"
                    @click="menuOpen = !menuOpen"
                >
                    <AppIcon :name="menuOpen ? 'close' : 'menu'" />
                </button>
            </div>
        </header>

        <main class="store__main">
            <RouterView />
        </main>

        <footer class="footer no-print">
            <div class="container">
                <div class="footer__grid">
                    <div>
                        <h4>{{ company.store_name }}</h4>
                        <p>{{ company.description }}</p>
                    </div>
                    <div>
                        <h4>Kontak</h4>
                        <p v-if="company.address" class="footer__line"><AppIcon name="map-pin" :size="16" /> {{ company.address }}</p>
                        <p v-if="company.phone" class="footer__line"><AppIcon name="phone" :size="16" /> {{ company.phone }}</p>
                        <p v-if="company.email" class="footer__line"><AppIcon name="mail" :size="16" /> {{ company.email }}</p>
                    </div>
                    <div>
                        <h4>Menu</h4>
                        <p><RouterLink :to="{ name: 'store.products' }">Semua Produk</RouterLink></p>
                        <p><RouterLink :to="{ name: 'store.orders' }">Lihat Pesanan</RouterLink></p>
                        <p><RouterLink :to="{ name: 'store.about' }">Tentang Kami</RouterLink></p>
                        <p><RouterLink :to="{ name: 'login' }">Masuk Staf</RouterLink></p>
                    </div>
                </div>
                <div class="footer__bottom">{{ company.footer_text || `© ${company.store_name}` }}</div>
            </div>
        </footer>
    </div>
</template>
