<script setup>
import { computed, onMounted, reactive, ref, watch } from 'vue';
import { useRoute, useRouter } from 'vue-router';
import http from '@/lib/http';
import AppIcon from '@/components/common/AppIcon.vue';
import AppPagination from '@/components/common/AppPagination.vue';
import SearchSelect from '@/components/common/SearchSelect.vue';
import ProductCard from '@/components/store/ProductCard.vue';
import { useToast } from '@/composables/useToast';
import { debounce, errorMessage } from '@/lib/utils';

const SORTS = ['latest', 'popular', 'price_asc', 'price_desc'];

const route = useRoute();
const router = useRouter();
const toast = useToast();

const filters = reactive({ categories: [], brands: [] });
const items = ref([]);
const loading = ref(false);
const meta = reactive({ page: 1, lastPage: 1, total: 0, from: 0, to: 0 });
const searchText = ref(typeof route.query.q === 'string' ? route.query.q : '');
let requestId = 0;

function toInt(value) {
    const number = Number.parseInt(value, 10);

    return Number.isInteger(number) && number > 0 ? number : null;
}

const query = computed(() => ({
    q: typeof route.query.q === 'string' ? route.query.q : '',
    kategori: toInt(route.query.kategori),
    merek: toInt(route.query.merek),
    urut: SORTS.includes(route.query.urut) ? route.query.urut : 'latest',
    hal: toInt(route.query.hal) ?? 1,
}));

function go(patch, replace = false) {
    const next = { ...route.query, ...patch };

    if (!('hal' in patch)) {
        delete next.hal;
    }

    for (const key of Object.keys(next)) {
        if (next[key] === null || next[key] === undefined || next[key] === '') {
            delete next[key];
        }
    }

    router[replace ? 'replace' : 'push']({ query: next });
}

async function load() {
    const current = ++requestId;
    loading.value = true;

    try {
        const { data } = await http.get('/store/products', {
            params: {
                search: query.value.q || undefined,
                category_id: query.value.kategori ?? undefined,
                brand_id: query.value.merek ?? undefined,
                sort: query.value.urut,
                page: query.value.hal,
                per_page: 12,
            },
        });

        if (current !== requestId) {
            return;
        }

        if (data.data.length === 0 && data.current_page > 1) {
            go({ hal: data.last_page }, true);
            return;
        }

        items.value = data.data;
        meta.page = data.current_page;
        meta.lastPage = data.last_page;
        meta.total = data.total;
        meta.from = data.from ?? 0;
        meta.to = data.to ?? 0;
    } catch (error) {
        if (current === requestId) {
            toast.error(errorMessage(error, 'Gagal memuat produk.'));
        }
    } finally {
        if (current === requestId) {
            loading.value = false;
        }
    }
}

onMounted(async () => {
    load();

    try {
        const { data } = await http.get('/store/filters');
        filters.categories = data.categories;
        filters.brands = data.brands;
    } catch {
        filters.categories = [];
        filters.brands = [];
    }
});

watch(query, () => {
    if (route.name === 'store.products') {
        load();
    }
});

watch(
    () => query.value.q,
    (value) => {
        if (value !== searchText.value.trim()) {
            searchText.value = value;
        }
    },
);

const applySearch = debounce((value) => go({ q: value.trim() || null }, true), 400);
watch(searchText, applySearch);

function reset() {
    searchText.value = '';
    router.push({ query: {} });
}
</script>

<template>
    <div class="container">
        <div class="page-title">
            <h1>Semua Produk</h1>
            <p>Temukan produk yang Anda butuhkan.</p>
        </div>

        <div class="catalog-toolbar">
            <div class="search-box">
                <span class="search-box__icon"><AppIcon name="search" :size="18" /></span>
                <input v-model="searchText" class="input" type="search" placeholder="Cari produk..." />
            </div>
            <SearchSelect
                :model-value="query.kategori"
                :options="filters.categories"
                placeholder="Semua kategori"
                search-placeholder="Cari kategori..."
                @update:model-value="go({ kategori: $event })"
            />
            <SearchSelect
                :model-value="query.merek"
                :options="filters.brands"
                placeholder="Semua merek"
                search-placeholder="Cari merek..."
                @update:model-value="go({ merek: $event })"
            />
            <select class="input" aria-label="Urutkan" :value="query.urut" @change="go({ urut: $event.target.value })">
                <option value="latest">Produk terbaru</option>
                <option value="popular">Produk populer</option>
                <option value="price_asc">Harga termurah</option>
                <option value="price_desc">Harga termahal</option>
            </select>
        </div>

        <div v-if="loading && items.length === 0" class="card loading-line">Memuat produk...</div>

        <div v-else-if="items.length === 0" class="card empty-state">
            <h3>Produk tidak ditemukan</h3>
            <p>Coba ubah kata kunci atau filter pencarian Anda.</p>
            <button class="btn btn-outline" type="button" @click="reset">Reset Pencarian</button>
        </div>

        <div v-else class="product-grid" :style="{ opacity: loading ? 0.6 : 1 }">
            <ProductCard v-for="product in items" :key="product.id" :product="product" />
        </div>

        <AppPagination
            :page="meta.page"
            :last-page="meta.lastPage"
            :total="meta.total"
            :from="meta.from"
            :to="meta.to"
            @change="go({ hal: $event })"
        />
    </div>
</template>
