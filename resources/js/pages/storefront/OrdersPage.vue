<script setup>
import { onMounted, ref } from 'vue';
import { useRouter } from 'vue-router';
import AppIcon from '@/components/common/AppIcon.vue';
import { getSavedOrders, removeSavedOrder } from '@/lib/orderStorage';
import { formatDateTime, formatRupiah } from '@/lib/utils';

const router = useRouter();

const saved = ref([]);
const number = ref('');
const token = ref('');

onMounted(() => {
    saved.value = getSavedOrders();
});

function open(order) {
    router.push({ name: 'store.order', params: { orderNumber: order.order_number }, hash: `#${order.token}` });
}

function forget(order) {
    removeSavedOrder(order.order_number);
    saved.value = getSavedOrders();
}

function lookup() {
    router.push({
        name: 'store.order',
        params: { orderNumber: number.value.trim().toUpperCase() },
        hash: `#${token.value.trim()}`,
    });
}
</script>

<template>
    <div class="container" style="max-width: 760px">
        <div class="page-title">
            <h1>Lihat Pesanan</h1>
            <p>Pantau status pesanan Anda.</p>
        </div>

        <section class="card" style="margin-bottom: 20px">
            <h3>Pesanan di Perangkat Ini</h3>

            <p v-if="saved.length === 0" class="muted" style="margin: 0">Belum ada pesanan tersimpan di perangkat ini.</p>

            <div v-else class="saved-list">
                <div v-for="order in saved" :key="order.order_number" class="saved-item">
                    <div>
                        <strong>{{ order.order_number }}</strong>
                        <div class="hint">{{ formatDateTime(order.created_at) }} · {{ formatRupiah(order.total_amount) }}</div>
                    </div>
                    <div class="table-actions">
                        <button class="btn btn-primary btn-sm" type="button" @click="open(order)">
                            <AppIcon name="eye" :size="16" /> Buka
                        </button>
                        <button class="btn btn-outline-danger btn-sm" type="button" aria-label="Hapus dari perangkat" @click="forget(order)">
                            <AppIcon name="trash" :size="16" />
                        </button>
                    </div>
                </div>
            </div>
        </section>

        <section class="card">
            <h3>Cari dengan Nomor Pesanan</h3>
            <form novalidate @submit.prevent="lookup">
                <div class="form-group">
                    <label class="form-label" for="o-number">Nomor Pesanan</label>
                    <input id="o-number" v-model="number" class="input" type="text" placeholder="BS-261120-XXXXX" autocomplete="off" />
                </div>
                <div class="form-group">
                    <label class="form-label" for="o-token">Token Akses</label>
                    <input id="o-token" v-model="token" class="input" type="text" autocomplete="off" />
                    <span class="hint">Token ada pada tautan yang Anda terima saat membuat pesanan.</span>
                </div>
                <button class="btn btn-primary" type="submit" :disabled="number.trim() === '' || token.trim() === ''">Cari Pesanan</button>
            </form>
        </section>
    </div>
</template>
