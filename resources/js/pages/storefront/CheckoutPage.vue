<script setup>
import { onMounted, reactive, ref } from 'vue';
import { useRouter } from 'vue-router';
import http from '@/lib/http';
import { useToast } from '@/composables/useToast';
import { useCartStore } from '@/stores/cart';
import { saveOrder } from '@/lib/orderStorage';
import { errorMessage, formatRupiah, validationErrors } from '@/lib/utils';
import { uuid } from '@/lib/uuid';

const router = useRouter();
const cart = useCartStore();
const toast = useToast();

const idempotencyKey = uuid();
const form = reactive({ customer_name: '', customer_phone: '', notes: '' });
const errors = ref({});
const stockIssues = ref([]);
const submitting = ref(false);
const preparing = ref(true);

onMounted(async () => {
    await cart.refresh();
    preparing.value = false;

    if (cart.isEmpty) {
        router.replace({ name: 'store.cart' });
    }
});

function validate() {
    const found = {};
    const name = form.customer_name.trim();
    const phone = form.customer_phone.replace(/[\s\-().]/g, '');

    if (name.length < 2) {
        found.customer_name = 'Nama minimal 2 karakter.';
    }

    if (!/^\+?[0-9]{8,15}$/.test(phone)) {
        found.customer_phone = 'Nomor telepon tidak valid (8-15 digit).';
    }

    if (form.notes.length > 500) {
        found.notes = 'Catatan maksimal 500 karakter.';
    }

    return found;
}

async function submit() {
    if (submitting.value) {
        return;
    }

    errors.value = validate();
    stockIssues.value = [];

    if (Object.keys(errors.value).length > 0) {
        return;
    }

    submitting.value = true;

    try {
        const { data } = await http.post('/store/orders', {
            customer_name: form.customer_name.trim(),
            customer_phone: form.customer_phone.trim(),
            notes: form.notes.trim() || null,
            idempotency_key: idempotencyKey,
            items: cart.items.map(({ product_id, quantity }) => ({ product_id, quantity })),
        });

        saveOrder({
            order_number: data.data.order_number,
            token: data.token,
            total_amount: data.data.total_amount,
            created_at: data.data.created_at,
        });

        cart.clear();
        toast.success('Pesanan berhasil dibuat.');

        router.replace({
            name: 'store.order',
            params: { orderNumber: data.data.order_number },
            hash: `#${data.token}`,
        });
    } catch (error) {
        const status = error.response?.status;

        if (status === 422) {
            const raw = error.response.data?.errors ?? {};

            errors.value = validationErrors(error);
            stockIssues.value = Array.isArray(raw.items) ? raw.items : [];

            if (stockIssues.value.length > 0) {
                await cart.refresh();
            }

            toast.error('Pesanan belum bisa dibuat. Periksa isian Anda.');
        } else if (status === 429) {
            toast.error('Terlalu banyak permintaan. Tunggu sebentar lalu coba lagi.');
        } else {
            toast.error(errorMessage(error, 'Gagal membuat pesanan. Coba lagi.'));
        }
    } finally {
        submitting.value = false;
    }
}
</script>

<template>
    <div class="container">
        <div class="page-title">
            <h1>Checkout</h1>
            <p>Isi data Anda untuk membuat pesanan.</p>
        </div>

        <div v-if="preparing" class="card loading-line">Menyiapkan pesanan...</div>

        <form v-else-if="!cart.isEmpty" class="checkout-layout" novalidate @submit.prevent="submit">
            <div class="card">
                <h3>Data Pemesan</h3>

                <div v-if="stockIssues.length" class="alert alert-danger" role="alert">
                    Ada perubahan pada pesanan Anda:
                    <ul>
                        <li v-for="issue in stockIssues" :key="issue">{{ issue }}</li>
                    </ul>
                    Keranjang sudah disesuaikan. Periksa ringkasan lalu coba lagi.
                </div>

                <div class="form-group">
                    <label class="form-label" for="c-name">Nama Customer</label>
                    <input id="c-name" v-model="form.customer_name" class="input" :class="{ 'is-invalid': errors.customer_name }" type="text" maxlength="100" autocomplete="name" />
                    <span v-if="errors.customer_name" class="form-error">{{ errors.customer_name }}</span>
                </div>

                <div class="form-group">
                    <label class="form-label" for="c-phone">Nomor Telepon</label>
                    <input id="c-phone" v-model="form.customer_phone" class="input" :class="{ 'is-invalid': errors.customer_phone }" type="tel" inputmode="tel" maxlength="25" autocomplete="tel" placeholder="08xxxxxxxxxx" />
                    <span v-if="errors.customer_phone" class="form-error">{{ errors.customer_phone }}</span>
                </div>

                <div class="form-group">
                    <label class="form-label" for="c-notes">Catatan Pesanan (opsional)</label>
                    <textarea id="c-notes" placeholder="Misalnya: No. Meja, tambahan, dan lain-lain..." v-model="form.notes" class="input" :class="{ 'is-invalid': errors.notes }" rows="3" maxlength="500"></textarea>
                    <span v-if="errors.notes" class="form-error">{{ errors.notes }}</span>
                </div>

                <div class="pay-note">
                    <strong>Metode pembayaran:</strong> pembayaran langsung di kasir.
                    Setelah pesanan dibuat, tunjukkan QR Code Anda kepada kasir.
                </div>
            </div>

            <aside class="card summary">
                <h3>Ringkasan Pesanan</h3>
                <ul class="summary__items">
                    <li v-for="line in cart.lines" :key="line.product_id">
                        <span>{{ line.name }}<br /><small class="muted">{{ line.quantity }} × {{ formatRupiah(line.unit_price) }}</small></span>
                        <strong>{{ formatRupiah(line.unit_price * line.quantity) }}</strong>
                    </li>
                </ul>
                <div class="summary__row summary__row--total">
                    <span>Total pembayaran</span>
                    <span>{{ formatRupiah(cart.total) }}</span>
                </div>
                <button class="btn btn-primary btn-block" type="submit" :disabled="submitting || cart.loading" style="margin-top: 14px">
                    {{ submitting ? 'Memproses...' : 'BUAT PESANAN' }}
                </button>
                <RouterLink class="btn btn-outline btn-block" style="margin-top: 10px" :to="{ name: 'store.cart' }">Kembali ke Keranjang</RouterLink>
            </aside>
        </form>
    </div>
</template>
