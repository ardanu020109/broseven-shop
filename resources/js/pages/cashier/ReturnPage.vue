<script setup>
import { computed, onMounted, reactive, ref, watch } from 'vue';
import { useRoute } from 'vue-router';
import http from '@/lib/http';
import AppIcon from '@/components/common/AppIcon.vue';
import AppModal from '@/components/common/AppModal.vue';
import AppPagination from '@/components/common/AppPagination.vue';
import ConfirmDialog from '@/components/common/ConfirmDialog.vue';
import { useToast } from '@/composables/useToast';
import { debounce, errorMessage, formatDateTime, formatNumber, formatRupiah, validationErrors } from '@/lib/utils';
import { uuid } from '@/lib/uuid';

const route = useRoute();
const toast = useToast();

const tab = ref('process');

const search = ref('');
const results = ref([]);
const resultsLoading = ref(false);
let resultsRequest = 0;

const order = ref(null);
const orderLoading = ref(false);
const qty = reactive({});
const reason = ref('');
const errors = ref({});
const issues = ref([]);
const submitting = ref(false);
const confirmOpen = ref(false);
const idempotencyKey = ref(uuid());

const history = ref([]);
const historyLoading = ref(false);
const historyMeta = reactive({ page: 1, lastPage: 1, total: 0, from: 0, to: 0 });
const historyFilters = reactive({ search: '', date_from: '', date_to: '' });
const detail = reactive({ open: false, loading: false, data: null });
let historyRequest = 0;

const quickReasons = ['Barang rusak atau cacat', 'Salah barang', 'Tidak sesuai pesanan', 'Customer berubah pikiran'];

const totalQty = computed(() =>
    order.value ? order.value.items.reduce((sum, item) => sum + (Number(qty[item.id]) || 0), 0) : 0,
);
const totalAmount = computed(() =>
    order.value ? order.value.items.reduce((sum, item) => sum + (Number(qty[item.id]) || 0) * item.unit_price, 0) : 0,
);
const canSubmit = computed(
    () => Boolean(order.value?.can_return) && totalQty.value > 0 && reason.value.trim().length >= 5 && !submitting.value,
);

async function loadResults() {
    const current = ++resultsRequest;
    resultsLoading.value = true;

    try {
        const { data } = await http.get('/kasir/orders', {
            params: { status: 'SELESAI', search: search.value || undefined, per_page: 8 },
        });

        if (current === resultsRequest) {
            results.value = data.data;
        }

        return data.data;
    } catch {
        return [];
    } finally {
        if (current === resultsRequest) {
            resultsLoading.value = false;
        }
    }
}

function applyOrder(data) {
    order.value = data;

    for (const key of Object.keys(qty)) {
        delete qty[key];
    }

    data.items.forEach((item) => {
        qty[item.id] = 0;
    });

    reason.value = '';
    errors.value = {};
    issues.value = [];
    idempotencyKey.value = uuid();
}

async function selectOrder(id) {
    orderLoading.value = true;

    try {
        const { data } = await http.get(`/kasir/orders/${id}`);
        applyOrder(data.data);
    } catch (error) {
        toast.error(errorMessage(error));
    } finally {
        orderLoading.value = false;
    }
}

async function refreshOrder() {
    if (!order.value) {
        return;
    }

    try {
        const { data } = await http.get(`/kasir/orders/${order.value.id}`);
        order.value = data.data;
        data.data.items.forEach((item) => clampQty(item));
    } catch {
        order.value = null;
    }
}

function clampQty(item) {
    const value = Math.trunc(Number(qty[item.id])) || 0;
    qty[item.id] = Math.max(0, Math.min(value, item.returnable_quantity));
}

function fillAll(item) {
    qty[item.id] = item.returnable_quantity;
}

async function submit() {
    confirmOpen.value = false;
    submitting.value = true;
    errors.value = {};
    issues.value = [];

    try {
        const { data } = await http.post(`/kasir/orders/${order.value.id}/returns`, {
            reason: reason.value.trim(),
            idempotency_key: idempotencyKey.value,
            items: order.value.items
                .filter((item) => Number(qty[item.id]) > 0)
                .map((item) => ({ order_item_id: item.id, quantity: Number(qty[item.id]) })),
        });

        toast.success(data.message);
        applyOrder(data.data);
        loadHistory();
    } catch (error) {
        const body = error.response?.data;

        errors.value = validationErrors(error);

        if (Array.isArray(body?.return_issues)) {
            issues.value = body.return_issues;
        }

        toast.error(body?.errors ? errorMessage(error) : (body?.message ?? errorMessage(error)));

        if (!body?.errors) {
            await refreshOrder();
        }
    } finally {
        submitting.value = false;
    }
}

async function loadHistory() {
    const current = ++historyRequest;
    historyLoading.value = true;

    try {
        const { data } = await http.get('/kasir/returns', {
            params: {
                page: historyMeta.page,
                search: historyFilters.search || undefined,
                date_from: historyFilters.date_from || undefined,
                date_to: historyFilters.date_to || undefined,
                per_page: 10,
            },
        });

        if (current !== historyRequest) {
            return;
        }

        history.value = data.data;
        historyMeta.page = data.current_page;
        historyMeta.lastPage = data.last_page;
        historyMeta.total = data.total;
        historyMeta.from = data.from ?? 0;
        historyMeta.to = data.to ?? 0;
    } catch (error) {
        if (current === historyRequest) {
            toast.error(errorMessage(error, 'Gagal memuat riwayat retur.'));
        }
    } finally {
        if (current === historyRequest) {
            historyLoading.value = false;
        }
    }
}

function changeHistoryPage(page) {
    historyMeta.page = page;
    loadHistory();
}

async function openDetail(item) {
    detail.open = true;
    detail.loading = true;
    detail.data = null;

    try {
        const { data } = await http.get(`/kasir/returns/${item.id}`);
        detail.data = data.data;
    } catch (error) {
        toast.error(errorMessage(error));
        detail.open = false;
    } finally {
        detail.loading = false;
    }
}

watch(search, debounce(loadResults, 400));
watch(tab, (value) => {
    if (value === 'history') {
        loadHistory();
    }
});
watch(
    () => [historyFilters.search, historyFilters.date_from, historyFilters.date_to],
    debounce(() => {
        historyMeta.page = 1;
        loadHistory();
    }, 400),
);

onMounted(async () => {
    const preset = typeof route.query.order === 'string' ? route.query.order : '';

    if (preset) {
        search.value = preset;
    }

    const found = await loadResults();
    const exact = found.find((item) => item.order_number === preset.toUpperCase());

    if (exact) {
        await selectOrder(exact.id);
    }
});
</script>

<template>
    <section>
        <div class="page-header">
            <div>
                <h1>Retur Barang</h1>
                <p>Stok bertambah sesuai jumlah barang yang benar-benar diterima kembali.</p>
            </div>
        </div>

        <div class="segmented" style="max-width: 360px; margin-bottom: 20px">
            <button type="button" :class="{ 'is-active': tab === 'process' }" @click="tab = 'process'">Proses Retur</button>
            <button type="button" :class="{ 'is-active': tab === 'history' }" @click="tab = 'history'">Riwayat Retur</button>
        </div>

        <div v-show="tab === 'process'" class="return-grid">
            <div class="return-side">
                <div class="card">
                    <h3>Cari Transaksi Selesai</h3>
                    <div class="search-box" style="margin-bottom: 12px">
                        <span class="search-box__icon"><AppIcon name="search" :size="18" /></span>
                        <input v-model="search" class="input" type="search" placeholder="Nomor pesanan / nama / telepon" />
                    </div>

                    <div class="queue-list">
                        <p v-if="resultsLoading && results.length === 0" class="loading-line" style="margin: 0">Memuat...</p>
                        <p v-else-if="results.length === 0" class="muted" style="margin: 0; text-align: center; padding: 16px 0">
                            Tidak ada transaksi selesai yang cocok.
                        </p>

                        <button
                            v-for="item in results"
                            :key="item.id"
                            type="button"
                            class="queue-item"
                            :class="{ 'queue-item--active': order?.id === item.id }"
                            @click="selectOrder(item.id)"
                        >
                            <span class="queue-item__row"><strong>{{ item.order_number }}</strong></span>
                            <span>{{ item.customer_name }}</span>
                            <small>{{ formatRupiah(item.total_amount) }} · {{ formatDateTime(item.completed_at) }}</small>
                        </button>
                    </div>
                </div>
            </div>

            <div style="min-width: 0">
                <div v-if="orderLoading" class="card loading-line">Memuat pesanan...</div>

                <div v-else-if="!order" class="card pos__empty">
                    <AppIcon name="refresh" :size="44" />
                    <h3 style="margin-top: 12px">Pilih transaksi</h3>
                    <p>Cari dan pilih transaksi yang barangnya akan diretur.</p>
                </div>

                <div v-else class="card">
                    <div class="order-head">
                        <div>
                            <h2 class="order-no">{{ order.order_number }}</h2>
                            <span class="muted">{{ order.customer_name }} · {{ order.customer_phone }}</span>
                            <div class="hint">Selesai {{ formatDateTime(order.completed_at) }}</div>
                        </div>
                    </div>

                    <div v-if="!order.can_return" class="banner banner--warn">
                        <AppIcon name="alert" :size="18" />
                        <span>Tidak ada barang yang dapat diretur untuk pesanan ini (semua sudah diretur atau batas waktu retur terlewati).</span>
                    </div>

                    <div v-if="issues.length" class="banner banner--bad" role="alert">
                        <AppIcon name="x-circle" :size="18" />
                        <div>
                            <strong>Jumlah retur melebihi sisa barang:</strong>
                            <ul>
                                <li v-for="issue in issues" :key="issue.order_item_id">
                                    {{ issue.name }}: diminta {{ issue.requested }}, dapat diretur {{ issue.returnable }}
                                </li>
                            </ul>
                        </div>
                    </div>

                    <div class="table-wrap">
                        <table class="table">
                            <thead>
                                <tr>
                                    <th>Barang</th>
                                    <th class="num">Harga</th>
                                    <th class="num">Dibeli</th>
                                    <th class="num">Sudah Diretur</th>
                                    <th class="num">Diterima Kembali</th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr v-for="item in order.items" :key="item.id">
                                    <td><strong>{{ item.name }}</strong><div class="hint">{{ item.code }}</div></td>
                                    <td class="num">{{ formatRupiah(item.unit_price) }}</td>
                                    <td class="num">{{ formatNumber(item.quantity) }}</td>
                                    <td class="num">{{ formatNumber(item.returned_quantity) }}</td>
                                    <td class="num">
                                        <span class="qty-cell">
                                            <input
                                                v-model.number="qty[item.id]"
                                                class="input qty-input"
                                                type="number"
                                                min="0"
                                                :max="item.returnable_quantity"
                                                step="1"
                                                :disabled="!order.can_return || item.returnable_quantity === 0"
                                                :aria-label="`Jumlah retur ${item.name}`"
                                                @change="clampQty(item)"
                                            />
                                            <button
                                                class="btn btn-outline btn-sm"
                                                type="button"
                                                :disabled="!order.can_return || item.returnable_quantity === 0"
                                                @click="fillAll(item)"
                                            >
                                                Semua ({{ item.returnable_quantity }})
                                            </button>
                                        </span>
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>

                    <div class="form-group" style="margin-top: 16px">
                        <label class="form-label" for="return-reason">Alasan retur (wajib)</label>
                        <div class="chip-row">
                            <button v-for="item in quickReasons" :key="item" class="chip-btn" type="button" @click="reason = item">{{ item }}</button>
                        </div>
                        <textarea id="return-reason" v-model="reason" class="input" :class="{ 'is-invalid': errors.reason }" rows="2" maxlength="500" :disabled="!order.can_return"></textarea>
                        <span v-if="errors.reason" class="form-error">{{ errors.reason }}</span>
                        <span v-else class="hint">Minimal 5 karakter.</span>
                    </div>

                    <div class="refund-box">
                        <div>
                            <div class="muted">{{ formatNumber(totalQty) }} barang diterima kembali</div>
                            <strong>{{ formatRupiah(totalAmount) }}</strong>
                            <div class="hint">Nilai retur untuk dikembalikan ke customer</div>
                        </div>
                        <button class="btn btn-primary btn-lg" type="button" :disabled="!canSubmit" @click="confirmOpen = true">
                            {{ submitting ? 'Memproses...' : 'Proses Retur' }}
                        </button>
                    </div>

                    <template v-if="order.returns.length">
                        <h3 style="margin: 22px 0 12px">Retur Sebelumnya</h3>
                        <div v-for="item in order.returns" :key="item.id" class="return-card">
                            <strong>{{ item.return_number }}</strong>
                            <span class="muted"> · {{ formatDateTime(item.created_at) }}</span>
                            <div class="hint">Alasan: {{ item.reason }}</div>
                            <ul>
                                <li v-for="line in item.items" :key="line.id">{{ line.quantity }} × {{ line.name }}</li>
                            </ul>
                            <strong>{{ formatRupiah(item.total_amount) }}</strong>
                        </div>
                    </template>
                </div>
            </div>
        </div>

        <div v-show="tab === 'history'">
            <div class="toolbar">
                <div class="search-box">
                    <span class="search-box__icon"><AppIcon name="search" :size="18" /></span>
                    <input v-model="historyFilters.search" class="input" type="search" placeholder="Cari nomor retur / pesanan / nama..." />
                </div>
                <input v-model="historyFilters.date_from" class="input filter" type="date" aria-label="Dari tanggal" />
                <input v-model="historyFilters.date_to" class="input filter" type="date" aria-label="Sampai tanggal" />
            </div>

            <div class="table-wrap">
                <table class="table">
                    <thead>
                        <tr>
                            <th>No. Retur</th>
                            <th>Pesanan</th>
                            <th>Customer</th>
                            <th class="num">Barang</th>
                            <th class="num">Nilai Retur</th>
                            <th>Waktu</th>
                            <th class="num">Aksi</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr v-if="historyLoading && history.length === 0">
                            <td colspan="7" class="loading-line">Memuat data...</td>
                        </tr>
                        <tr v-else-if="history.length === 0">
                            <td colspan="7" class="table-empty">Belum ada retur.</td>
                        </tr>
                        <tr v-for="item in history" :key="item.id">
                            <td class="nowrap"><strong>{{ item.return_number }}</strong></td>
                            <td class="nowrap">{{ item.order_number }}</td>
                            <td>{{ item.customer_name }}</td>
                            <td class="num">{{ formatNumber(item.total_quantity) }}</td>
                            <td class="num">{{ formatRupiah(item.total_amount) }}</td>
                            <td class="nowrap">{{ formatDateTime(item.created_at) }}</td>
                            <td>
                                <div class="table-actions">
                                    <button class="btn btn-outline btn-sm" type="button" @click="openDetail(item)">
                                        <AppIcon name="eye" :size="16" /> Detail
                                    </button>
                                </div>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>

            <AppPagination
                :page="historyMeta.page"
                :last-page="historyMeta.lastPage"
                :total="historyMeta.total"
                :from="historyMeta.from"
                :to="historyMeta.to"
                @change="changeHistoryPage"
            />
        </div>

        <ConfirmDialog
            v-model="confirmOpen"
            title="Proses Retur"
            :message="`Proses retur ${formatNumber(totalQty)} barang senilai ${formatRupiah(totalAmount)}? Stok akan bertambah dan tindakan ini tidak dapat dibatalkan.`"
            confirm-text="Ya, Proses Retur"
            :loading="submitting"
            @confirm="submit"
        />

        <AppModal v-model="detail.open" title="Detail Retur" width="620px">
            <p v-if="detail.loading" class="loading-line" style="margin: 0">Memuat detail...</p>
            <div v-else-if="detail.data">
                <p style="margin-top: 0">
                    <strong>{{ detail.data.return_number }}</strong><br />
                    <span class="muted">Pesanan {{ detail.data.order_number }} · {{ detail.data.customer_name }}</span><br />
                    <span class="muted">{{ formatDateTime(detail.data.created_at) }} · {{ detail.data.user }}</span>
                </p>
                <p><strong>Alasan:</strong> {{ detail.data.reason }}</p>
                <div class="table-wrap">
                    <table class="table">
                        <thead>
                            <tr><th>Barang</th><th class="num">Harga</th><th class="num">Jumlah</th><th class="num">Subtotal</th></tr>
                        </thead>
                        <tbody>
                            <tr v-for="line in detail.data.items" :key="line.id">
                                <td>{{ line.name }}</td>
                                <td class="num">{{ formatRupiah(line.unit_price) }}</td>
                                <td class="num">{{ formatNumber(line.quantity) }}</td>
                                <td class="num">{{ formatRupiah(line.subtotal) }}</td>
                            </tr>
                        </tbody>
                    </table>
                </div>
                <div class="summary__row summary__row--total">
                    <span>Nilai retur</span>
                    <span>{{ formatRupiah(detail.data.total_amount) }}</span>
                </div>
            </div>
        </AppModal>
    </section>
</template>
