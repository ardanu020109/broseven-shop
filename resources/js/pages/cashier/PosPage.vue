<script setup>
import { nextTick, onBeforeUnmount, onMounted, reactive, ref, watch } from 'vue';
import http from '@/lib/http';
import AppIcon from '@/components/common/AppIcon.vue';
import ConfirmDialog from '@/components/common/ConfirmDialog.vue';
import CancelOrderModal from '@/components/pos/CancelOrderModal.vue';
import OrderDetailPanel from '@/components/pos/OrderDetailPanel.vue';
import QrScannerPanel from '@/components/pos/QrScannerPanel.vue';
import OrderStatusBadge from '@/components/store/OrderStatusBadge.vue';
import { useToast } from '@/composables/useToast';
import { debounce, errorMessage, formatDateTime, formatRupiah, validationErrors } from '@/lib/utils';

const POLL_MS = 15000;

const toast = useToast();

const tab = ref('DRAFT');
const queue = ref([]);
const queueLoading = ref(false);
const queueSearch = ref('');
const counts = reactive({ draft: 0, waiting: 0 });

const order = ref(null);
const via = ref('manual');
const barcodeInfo = ref(null);
const stockIssues = ref([]);
const feedback = ref(null);
const manualCode = ref('');
const manualInput = ref(null);
const mainEl = ref(null);

const lookupBusy = ref(false);
const acting = ref(false);
const approveOpen = ref(false);
const completeOpen = ref(false);
const cancelOpen = ref(false);
const cancelError = ref('');

let timer = null;
let queueRequest = 0;

async function loadQueue() {
    const current = ++queueRequest;
    queueLoading.value = true;

    try {
        const { data } = await http.get('/kasir/orders', {
            params: { status: tab.value, search: queueSearch.value || undefined, per_page: 20 },
        });

        if (current === queueRequest) {
            queue.value = data.data;
        }
    } catch {
        // gagal memuat antrean saat polling, abaikan
    } finally {
        if (current === queueRequest) {
            queueLoading.value = false;
        }
    }
}

async function loadCounts() {
    try {
        const { data } = await http.get('/kasir/orders/counts');
        counts.draft = data.draft;
        counts.waiting = data.waiting;
    } catch {
        // abaikan
    }
}

function refreshAll() {
    return Promise.all([loadQueue(), loadCounts()]);
}

function show(data, source, barcode) {
    order.value = data;
    via.value = source;
    barcodeInfo.value = barcode;
    stockIssues.value = [];

    if (window.innerWidth < 992) {
        nextTick(() => mainEl.value?.scrollIntoView({ behavior: 'smooth', block: 'start' }));
    }
}

async function openFromQueue(item) {
    try {
        const { data } = await http.get(`/kasir/orders/${item.id}`);
        show(data.data, 'manual', null);
    } catch (error) {
        toast.error(errorMessage(error));
        await refreshAll();
    }
}

async function reloadCurrent() {
    if (!order.value) {
        return;
    }

    try {
        const { data } = await http.get(`/kasir/orders/${order.value.id}`);
        order.value = data.data;
    } catch {
        order.value = null;
    }
}

async function lookup(code) {
    if (lookupBusy.value) {
        return;
    }

    lookupBusy.value = true;
    feedback.value = null;

    try {
        const { data } = await http.post('/kasir/orders/lookup', { code });
        const scanned = data.matched_by === 'barcode';

        show(data.data, scanned ? 'scan' : 'manual', data.barcode);

        if (scanned && data.barcode && !data.barcode.valid) {
            feedback.value = { type: 'warning', text: data.barcode.reason };
        } else if (scanned) {
            feedback.value = { type: 'success', text: `Barcode valid. Pesanan ${data.data.order_number} ditemukan.` };
        } else {
            feedback.value = { type: 'success', text: `Pesanan ${data.data.order_number} ditemukan.` };
        }
    } catch (error) {
        feedback.value = { type: 'error', text: error.response?.data?.message ?? errorMessage(error) };
    } finally {
        lookupBusy.value = false;
    }
}

function onScan(code) {
    lookup(code);
}

async function submitManual() {
    const code = manualCode.value.trim();

    if (code === '') {
        return;
    }

    await lookup(code);
    manualCode.value = '';
    manualInput.value?.focus();
}

async function runAction(kind, payload = {}) {
    acting.value = true;
    stockIssues.value = [];

    try {
        const { data } = await http.post(`/kasir/orders/${order.value.id}/${kind}`, payload);

        order.value = data.data;
        barcodeInfo.value = null;
        toast.success(data.message);
        await refreshAll();

        return { ok: true, errors: {} };
    } catch (error) {
        const body = error.response?.data;

        if (Array.isArray(body?.stock_issues)) {
            stockIssues.value = body.stock_issues;
        }

        toast.error(Array.isArray(body?.stock_issues) ? body.message : errorMessage(error));

        await reloadCurrent();
        await refreshAll();

        return { ok: false, errors: validationErrors(error) };
    } finally {
        acting.value = false;
    }
}

async function doApprove() {
    await runAction('approve', { via: via.value });
    approveOpen.value = false;
}

async function doComplete() {
    await runAction('complete');
    completeOpen.value = false;
}

async function doCancel(reason) {
    cancelError.value = '';

    const result = await runAction('cancel', { reason });

    if (result.ok) {
        cancelOpen.value = false;
    } else if (result.errors.reason) {
        cancelError.value = result.errors.reason;
    } else {
        cancelOpen.value = false;
    }
}

function openCancel() {
    cancelError.value = '';
    cancelOpen.value = true;
}

function clearOrder() {
    order.value = null;
    barcodeInfo.value = null;
    stockIssues.value = [];
    feedback.value = null;
    manualInput.value?.focus();
}

watch(tab, loadQueue);
watch(queueSearch, debounce(loadQueue, 400));

onMounted(() => {
    refreshAll();
    manualInput.value?.focus();

    timer = setInterval(() => {
        if (!document.hidden) {
            refreshAll();
        }
    }, POLL_MS);
});

onBeforeUnmount(() => clearInterval(timer));
</script>

<template>
    <section>
        <div class="page-header">
            <div>
                <h1>Pelayanan Kasir</h1>
                <p>Pindai QR Code pesanan customer atau cari dengan nomor pesanan.</p>
            </div>
            <div class="page-actions">
                <button class="btn btn-outline" type="button" @click="refreshAll">
                    <AppIcon name="refresh" :size="18" /> Muat Ulang Antrean
                </button>
            </div>
        </div>

        <div class="pos">
            <div class="pos__side">
                <div class="card">
                    <h3>Pindai Barcode</h3>
                    <QrScannerPanel @scan="onScan" />

                    <form class="manual" novalidate @submit.prevent="submitManual">
                        <input
                            ref="manualInput"
                            v-model="manualCode"
                            class="input"
                            type="text"
                            placeholder="Kode QR / BS-YYMMDD-XXXXX"
                            autocomplete="off"
                            spellcheck="false"
                            aria-label="Kode pesanan atau barcode"
                        />
                        <button class="btn btn-primary" type="submit" :disabled="lookupBusy || manualCode.trim() === ''">
                            <AppIcon name="search" :size="18" /> Cari
                        </button>
                    </form>
                    <p class="hint" style="margin: 6px 0 0">Scanner USB juga bisa dipakai: arahkan kursor ke kolom di atas lalu scan.</p>

                    <div v-if="feedback" class="scan-feedback" :class="`scan-feedback--${feedback.type}`" role="status">
                        <AppIcon :name="feedback.type === 'success' ? 'check-circle' : feedback.type === 'warning' ? 'alert' : 'x-circle'" :size="18" />
                        <span>{{ feedback.text }}</span>
                    </div>
                </div>

                <div class="card">
                    <div class="queue-head">
                        <div class="segmented" style="flex: 1">
                            <button type="button" :class="{ 'is-active': tab === 'DRAFT' }" @click="tab = 'DRAFT'">
                                Draft ({{ counts.draft }})
                            </button>
                            <button type="button" :class="{ 'is-active': tab === 'MENUNGGU' }" @click="tab = 'MENUNGGU'">
                                Menunggu ({{ counts.waiting }})
                            </button>
                        </div>
                    </div>

                    <div class="search-box" style="margin-bottom: 12px">
                        <span class="search-box__icon"><AppIcon name="search" :size="18" /></span>
                        <input v-model="queueSearch" class="input" type="search" placeholder="Cari nomor / nama..." />
                    </div>

                    <div class="queue-list">
                        <p v-if="queueLoading && queue.length === 0" class="loading-line" style="margin: 0">Memuat antrean...</p>
                        <p v-else-if="queue.length === 0" class="muted" style="margin: 0; text-align: center; padding: 16px 0">
                            {{ tab === 'DRAFT' ? 'Belum ada pesanan draft.' : 'Tidak ada pesanan menunggu pembayaran.' }}
                        </p>

                        <button
                            v-for="item in queue"
                            :key="item.id"
                            type="button"
                            class="queue-item"
                            :class="{ 'queue-item--active': order?.id === item.id }"
                            @click="openFromQueue(item)"
                        >
                            <span class="queue-item__row">
                                <strong>{{ item.order_number }}</strong>
                                <OrderStatusBadge :status="item.status" :label="item.status_label" />
                            </span>
                            <span>{{ item.customer_name }}</span>
                            <small>{{ formatRupiah(item.total_amount) }} · {{ item.total_quantity }} barang · {{ formatDateTime(item.created_at) }}</small>
                        </button>
                    </div>
                </div>
            </div>

            <div ref="mainEl" class="pos__main">
                <div v-if="!order" class="card pos__empty">
                    <AppIcon name="scan" :size="44" />
                    <h3 style="margin-top: 12px">Belum ada pesanan dipilih</h3>
                    <p>Scan QR Code customer, ketik nomor pesanan, atau pilih dari antrean.</p>
                </div>

                <template v-else>
                    <OrderDetailPanel
                        :order="order"
                        :via="via"
                        :barcode="barcodeInfo"
                        :issues="stockIssues"
                        :busy="acting"
                        @approve="approveOpen = true"
                        @complete="completeOpen = true"
                        @cancel="openCancel"
                        @refresh="reloadCurrent"
                        @close="clearOrder"
                    />

                    <div v-if="['SELESAI', 'DIBATALKAN'].includes(order.status)" style="margin-top: 14px">
                        <button class="btn btn-primary" type="button" @click="clearOrder">
                            <AppIcon name="scan" :size="18" /> Pesanan Berikutnya
                        </button>
                    </div>
                </template>
            </div>
        </div>

        <ConfirmDialog
            v-model="approveOpen"
            title="Setujui Pesanan"
            :message="`Setujui pesanan ${order?.order_number ?? ''}? Stok barang akan dikurangi dan barcode tidak dapat dipakai lagi.`"
            confirm-text="Ya, Setujui"
            :loading="acting"
            @confirm="doApprove"
        />

        <ConfirmDialog
            v-model="completeOpen"
            title="Selesaikan Transaksi"
            :message="`Pastikan pembayaran ${formatRupiah(order?.total_amount ?? 0)} sudah diterima dari customer. Selesaikan transaksi ${order?.order_number ?? ''}?`"
            confirm-text="Ya, Pembayaran Diterima"
            :loading="acting"
            @confirm="doComplete"
        />

        <CancelOrderModal
            v-model="cancelOpen"
            :order="order"
            :loading="acting"
            :error="cancelError"
            @confirm="doCancel"
        />
    </section>
</template>
