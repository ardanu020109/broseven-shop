<script setup>
import { computed, onBeforeUnmount, ref, watch } from 'vue';
import { useRoute } from 'vue-router';
import http from '@/lib/http';
import AppIcon from '@/components/common/AppIcon.vue';
import AppModal from '@/components/common/AppModal.vue';
import OrderStatusBadge from '@/components/store/OrderStatusBadge.vue';
import QrCode from '@/components/store/QrCode.vue';
import { useBranding } from '@/composables/useBranding';
import { useToast } from '@/composables/useToast';
import { findSavedToken, saveOrder } from '@/lib/orderStorage';
import { downloadReceipt } from '@/lib/receipt';
import { errorMessage, formatDateTime, formatNumber, formatRupiah } from '@/lib/utils';

const POLL_MS = 15000;
const FINAL = ['SELESAI', 'DIBATALKAN'];

const route = useRoute();
const branding = useBranding();
const toast = useToast();

const orderNumber = computed(() => String(route.params.orderNumber ?? '').toUpperCase());
const token = ref('');
const order = ref(null);
const loading = ref(true);
const notFound = ref(false);
const needToken = ref(false);
const tokenInput = ref('');
const qrOpen = ref(false);
const downloading = ref(false);
let timer = null;

const steps = [
    { key: 'DRAFT', label: 'Pesanan dibuat' },
    { key: 'MENUNGGU', label: 'Disetujui kasir' },
    { key: 'SELESAI', label: 'Selesai' },
];

const currentStep = computed(() => ({ DRAFT: 0, MENUNGGU: 1, SELESAI: 2 })[order.value?.status] ?? -1);

const expiresAt = computed(() => {
    const hours = Number(branding.company.draft_expiry_hours ?? 0);

    if (!order.value || order.value.status !== 'DRAFT' || hours <= 0) {
        return null;
    }

    return new Date(new Date(order.value.created_at).getTime() + hours * 3600 * 1000);
});

function resolveToken() {
    const fromHash = route.hash.replace(/^#/, '');

    return fromHash || findSavedToken(orderNumber.value) || '';
}

function schedulePoll() {
    clearTimeout(timer);

    if (!order.value || FINAL.includes(order.value.status)) {
        return;
    }

    timer = setTimeout(() => {
        if (document.hidden) {
            schedulePoll();
        } else {
            load({ silent: true });
        }
    }, POLL_MS);
}

async function load({ silent = false } = {}) {
    if (!token.value) {
        needToken.value = true;
        loading.value = false;
        return;
    }

    if (!silent) {
        loading.value = true;
    }

    try {
        const { data } = await http.get(`/store/orders/${encodeURIComponent(orderNumber.value)}`, {
            headers: { 'X-Order-Token': token.value },
            skipAuthRedirect: true,
        });

        const previous = order.value?.status;

        order.value = data.data;
        notFound.value = false;
        needToken.value = false;

        saveOrder({
            order_number: data.data.order_number,
            token: token.value,
            total_amount: data.data.total_amount,
            created_at: data.data.created_at,
        });

        if (previous && previous !== data.data.status) {
            toast.success(`Status pesanan diperbarui: ${data.data.status_label}.`);
        }
    } catch (error) {
        if (silent && error.response?.status === 404) {
            notFound.value = true;
            order.value = null;
        } else if (!silent) {
            if (error.response?.status === 404) {
                notFound.value = true;
            } else {
                toast.error(errorMessage(error, 'Gagal memuat pesanan.'));
            }
        }
    } finally {
        loading.value = false;
        schedulePoll();
    }
}

function init() {
    clearTimeout(timer);
    order.value = null;
    notFound.value = false;
    needToken.value = false;
    token.value = resolveToken();
    load();
}

watch(() => route.fullPath, init, { immediate: true });
onBeforeUnmount(() => clearTimeout(timer));

function submitToken() {
    token.value = tokenInput.value.trim();
    notFound.value = false;
    load();
}

function print() {
    window.print();
}

async function download() {
    downloading.value = true;

    try {
        await downloadReceipt(order.value, branding.company.store_name);
    } catch (error) {
        toast.error(errorMessage(error, 'Gagal mengunduh bukti pesanan.'));
    } finally {
        downloading.value = false;
    }
}
</script>

<template>
    <div class="container">
        <div v-if="loading" class="card loading-line">Memuat pesanan...</div>

        <div v-else-if="needToken" class="card empty-state">
            <h3>Token akses diperlukan</h3>
            <p>Masukkan token akses untuk pesanan <strong>{{ orderNumber }}</strong>. Token ada pada tautan yang Anda terima saat membuat pesanan.</p>
            <form style="max-width: 420px; margin: 0 auto" @submit.prevent="submitToken">
                <input v-model="tokenInput" class="input" type="text" placeholder="Token akses" autocomplete="off" />
                <button class="btn btn-primary btn-block" type="submit" style="margin-top: 12px" :disabled="tokenInput.trim() === ''">Lihat Pesanan</button>
            </form>
        </div>

        <div v-else-if="notFound || !order" class="card empty-state">
            <h3>Pesanan tidak ditemukan</h3>
            <p>Nomor pesanan atau token akses tidak valid, atau pesanan draft sudah kedaluwarsa dan dihapus otomatis.</p>
            <RouterLink class="btn btn-primary" :to="{ name: 'store.orders' }">Lihat Pesanan Saya</RouterLink>
        </div>

        <template v-else>
            <div class="page-title no-print">
                <h1>Konfirmasi Pesanan</h1>
                <p>Simpan halaman ini untuk memantau status pesanan Anda.</p>
            </div>

            <div class="order-layout">
                <div class="card status-card">
                    <OrderStatusBadge :status="order.status" :label="order.status_label" />
                    <h1>{{ order.order_number }}</h1>
                    <p>{{ order.status_description }}</p>

                    <template v-if="order.status === 'DRAFT' && order.barcode_code">
                        <QrCode :value="order.barcode_code" :size="240" />
                        <p class="qr-code">{{ order.barcode_code }}</p>
                        <div class="alert alert-success" style="margin: 14px 0 0; text-align: left">
                            Tunjukkan QR Code ini kepada kasir untuk divalidasi. Pembayaran dilakukan langsung di kasir.
                            <template v-if="expiresAt"><br />Pesanan ini dihapus otomatis bila belum divalidasi kasir sebelum {{ formatDateTime(expiresAt) }}.</template>
                        </div>
                    </template>

                    <div v-else-if="order.status === 'MENUNGGU'" class="alert alert-success" style="text-align: left">
                        Pesanan sudah disetujui kasir. Selesaikan pembayaran di kasir.
                    </div>

                    <div v-else-if="order.status === 'SELESAI'" class="alert alert-success" style="text-align: left">
                        Terima kasih! Pembayaran telah diterima.
                    </div>

                    <div v-else-if="order.status === 'DIBATALKAN'" class="alert alert-danger" style="text-align: left">
                        Pesanan dibatalkan.
                        <div v-if="order.cancel_reason">Alasan: {{ order.cancel_reason }}</div>
                    </div>

                    <div class="order-actions no-print" style="justify-content: center">
                        <button v-if="order.status === 'DRAFT' && order.barcode_code" class="btn btn-secondary" type="button" @click="qrOpen = true">
                            <AppIcon name="qr" :size="18" /> Lihat Barcode
                        </button>
                        <button class="btn btn-outline" type="button" @click="print">
                            <AppIcon name="printer" :size="18" /> Cetak Pesanan
                        </button>
                        <button class="btn btn-outline" type="button" :disabled="downloading" @click="download">
                            <AppIcon name="download" :size="18" /> {{ downloading ? 'Menyiapkan...' : 'Unduh Bukti Pesanan' }}
                        </button>
                    </div>
                </div>

                <div style="display: flex; flex-direction: column; gap: 20px">
                    <div v-if="order.status !== 'DIBATALKAN'" class="card">
                        <h3>Status Pesanan</h3>
                        <div class="timeline">
                            <div
                                v-for="(step, index) in steps"
                                :key="step.key"
                                class="timeline__step"
                                :class="{ 'is-done': index < currentStep, 'is-current': index === currentStep }"
                            >
                                <span class="timeline__dot"><AppIcon v-if="index <= currentStep" name="check" :size="14" /></span>
                                <span class="timeline__label">{{ step.label }}</span>
                            </div>
                        </div>
                    </div>

                    <div class="card">
                        <h3>Detail Pemesan</h3>
                        <dl class="kv">
                            <div><dt>Nama</dt><dd>{{ order.customer_name }}</dd></div>
                            <div><dt>Telepon</dt><dd>{{ order.customer_phone }}</dd></div>
                            <div><dt>Dibuat</dt><dd>{{ formatDateTime(order.created_at) }}</dd></div>
                            <div v-if="order.approved_at"><dt>Disetujui</dt><dd>{{ formatDateTime(order.approved_at) }}</dd></div>
                            <div v-if="order.completed_at"><dt>Selesai</dt><dd>{{ formatDateTime(order.completed_at) }}</dd></div>
                            <div v-if="order.cancelled_at"><dt>Dibatalkan</dt><dd>{{ formatDateTime(order.cancelled_at) }}</dd></div>
                            <div><dt>Pembayaran</dt><dd>{{ order.payment_method_label }}</dd></div>
                            <div v-if="order.notes" style="grid-column: 1 / -1"><dt>Catatan</dt><dd>{{ order.notes }}</dd></div>
                        </dl>
                    </div>

                    <div class="card">
                        <h3>Daftar Barang</h3>
                        <div class="table-wrap">
                            <table class="table">
                                <thead>
                                    <tr>
                                        <th>Barang</th>
                                        <th class="num">Harga</th>
                                        <th class="num">Jumlah</th>
                                        <th class="num">Subtotal</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <tr v-for="(item, index) in order.items" :key="index">
                                        <td><strong>{{ item.product_name }}</strong></td>
                                        <td class="num">{{ formatRupiah(item.unit_price) }}</td>
                                        <td class="num">{{ formatNumber(item.quantity) }}</td>
                                        <td class="num">{{ formatRupiah(item.subtotal) }}</td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                        <div class="summary__row summary__row--total">
                            <span>Total pembayaran</span>
                            <span>{{ formatRupiah(order.total_amount) }}</span>
                        </div>
                    </div>

                    <div class="no-print">
                        <RouterLink class="btn btn-outline" :to="{ name: 'store.products' }">Lanjut Belanja</RouterLink>
                    </div>
                </div>
            </div>

            <AppModal v-model="qrOpen" title="Barcode Pesanan" width="460px">
                <div class="modal-qr">
                    <QrCode v-if="order.barcode_code" :value="order.barcode_code" :size="360" />
                    <p class="qr-code">{{ order.order_number }}</p>
                    <p class="hint">Tunjukkan QR Code ini kepada kasir.</p>
                </div>
            </AppModal>
        </template>
    </div>
</template>
