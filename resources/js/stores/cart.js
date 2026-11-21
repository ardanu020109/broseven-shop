import { computed, ref } from 'vue';
import { defineStore } from 'pinia';
import http from '@/lib/http';
import { useToast } from '@/composables/useToast';

const STORAGE_KEY = 'broseven_cart_v1';
const MAX_LINES = 50;

function readItems() {
    try {
        const parsed = JSON.parse(localStorage.getItem(STORAGE_KEY) ?? '[]');

        if (!Array.isArray(parsed)) {
            return [];
        }

        return parsed
            .filter((item) => Number.isInteger(item?.product_id) && Number.isInteger(item?.quantity) && item.quantity > 0)
            .slice(0, MAX_LINES);
    } catch {
        return [];
    }
}

function writeItems(items) {
    try {
        localStorage.setItem(STORAGE_KEY, JSON.stringify(items));
    } catch {
        // penyimpanan tidak tersedia, abaikan
    }
}

export const useCartStore = defineStore('cart', () => {
    const toast = useToast();

    const items = ref(readItems());
    const lines = ref([]);
    const loading = ref(false);
    const error = ref(false);
    const ready = ref(items.value.length === 0);

    let requestId = 0;
    let timer = null;

    const count = computed(() => items.value.reduce((sum, item) => sum + item.quantity, 0));
    const total = computed(() => lines.value.reduce((sum, line) => sum + line.unit_price * line.quantity, 0));
    const isEmpty = computed(() => items.value.length === 0);

    function persist() {
        writeItems(items.value.map(({ product_id, quantity }) => ({ product_id, quantity })));
    }

    function touch() {
        requestId += 1;
        clearTimeout(timer);
        timer = setTimeout(refresh, 300);
    }

    async function refresh() {
        clearTimeout(timer);

        if (items.value.length === 0) {
            requestId += 1;
            lines.value = [];
            loading.value = false;
            error.value = false;
            ready.value = true;
            return;
        }

        const current = ++requestId;
        loading.value = true;

        try {
            const { data } = await http.post('/store/cart/validate', {
                items: items.value.map(({ product_id, quantity }) => ({ product_id, quantity })),
            });

            if (current !== requestId) {
                return;
            }

            lines.value = data.lines;
            items.value = data.lines.map((line) => ({ product_id: line.product_id, quantity: line.quantity }));
            error.value = false;
            persist();

            data.removed.forEach((removed) => {
                toast.error(
                    removed.reason === 'out_of_stock'
                        ? `${removed.name} habis dan dihapus dari keranjang.`
                        : 'Ada produk yang tidak tersedia dan dihapus dari keranjang.',
                );
            });

            data.lines
                .filter((line) => line.adjusted)
                .forEach((line) => {
                    toast.error(`Jumlah ${line.name} disesuaikan menjadi ${line.quantity} sesuai stok.`);
                });
        } catch {
            if (current === requestId) {
                error.value = true;
            }
        } finally {
            if (current === requestId) {
                loading.value = false;
                ready.value = true;
            }
        }
    }

    function init() {
        if (items.value.length > 0) {
            refresh();
        }
    }

    function quantityOf(productId) {
        return items.value.find((item) => item.product_id === productId)?.quantity ?? 0;
    }

    function add(product, quantity = 1) {
        if (!product.in_stock || product.stock <= 0) {
            return { ok: false, message: 'Stok produk habis.' };
        }

        const existing = items.value.find((item) => item.product_id === product.id);
        const current = existing?.quantity ?? 0;

        if (current >= product.stock) {
            return { ok: false, message: 'Jumlah di keranjang sudah mencapai stok tersedia.' };
        }

        const next = Math.min(current + quantity, product.stock);

        if (existing) {
            existing.quantity = next;
        } else {
            if (items.value.length >= MAX_LINES) {
                return { ok: false, message: 'Keranjang penuh (maksimal 50 jenis barang).' };
            }

            items.value.push({ product_id: product.id, quantity: next });
        }

        persist();
        touch();

        return { ok: true, quantity: next };
    }

    function setQuantity(productId, quantity) {
        const item = items.value.find((entry) => entry.product_id === productId);

        if (!item) {
            return;
        }

        const line = lines.value.find((entry) => entry.product_id === productId);
        const max = line?.stock ?? 1000;
        const next = Math.max(1, Math.min(Math.trunc(Number(quantity)) || 1, max));

        item.quantity = next;

        if (line) {
            line.quantity = next;
        }

        persist();
        touch();
    }

    function remove(productId) {
        items.value = items.value.filter((item) => item.product_id !== productId);
        lines.value = lines.value.filter((line) => line.product_id !== productId);
        persist();
        touch();
    }

    function clear() {
        requestId += 1;
        clearTimeout(timer);
        items.value = [];
        lines.value = [];
        loading.value = false;
        error.value = false;
        ready.value = true;
        persist();
    }

    return {
        items,
        lines,
        loading,
        error,
        ready,
        count,
        total,
        isEmpty,
        init,
        refresh,
        quantityOf,
        add,
        setQuantity,
        remove,
        clear,
    };
});
