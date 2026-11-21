<script setup>
import { computed } from 'vue';
import AppIcon from '@/components/common/AppIcon.vue';
import OrderStatusBadge from '@/components/store/OrderStatusBadge.vue';
import { formatDateTime, formatNumber, formatRupiah } from '@/lib/utils';

const props = defineProps({
    order: { type: Object, required: true },
    via: { type: String, default: 'manual' },
    barcode: { type: Object, default: null },
    issues: { type: Array, default: () => [] },
    busy: { type: Boolean, default: false },
    readonly: { type: Boolean, default: false },
});

const emit = defineEmits(['approve', 'complete', 'cancel', 'refresh', 'close']);

const isDraft = computed(() => props.order.status === 'DRAFT');
const isFinal = computed(() => ['SELESAI', 'DIBATALKAN'].includes(props.order.status));
const showReturned = computed(() => props.order.status === 'SELESAI');
</script>

<template>
    <div class="card">
        <div class="order-head">
            <div>
                <h2 class="order-no">{{ order.order_number }}</h2>
                <OrderStatusBadge :status="order.status" :label="order.status_label" />
                <p class="hint" style="margin: 6px 0 0">{{ order.status_description }}</p>
            </div>

            <div v-if="!readonly" class="action-row">
                <button class="btn btn-outline btn-sm" type="button" :disabled="busy" @click="emit('refresh')">
                    <AppIcon name="refresh" :size="16" /> Muat Ulang
                </button>
                <button class="btn btn-outline btn-sm" type="button" @click="emit('close')">
                    <AppIcon name="close" :size="16" /> Tutup
                </button>
            </div>
        </div>

        <div v-if="barcode" class="banner" :class="barcode.valid ? 'banner--ok' : 'banner--warn'">
            <AppIcon :name="barcode.valid ? 'check-circle' : 'alert'" :size="18" />
            <span>{{ barcode.valid ? 'Barcode valid dan belum pernah digunakan.' : barcode.reason }}</span>
        </div>

        <div v-else-if="!readonly && isDraft && via === 'manual'" class="banner banner--warn">
            <AppIcon name="alert" :size="18" />
            <span>Pesanan dibuka lewat nomor pesanan, bukan scan barcode. Pastikan identitas customer sesuai.</span>
        </div>

        <div v-if="issues.length" class="banner banner--bad" role="alert">
            <AppIcon name="x-circle" :size="18" />
            <div>
                <strong>Persetujuan ditolak. Stok tidak mencukupi:</strong>
                <ul>
                    <li v-for="issue in issues" :key="issue.product_id">
                        {{ issue.name }}: diminta {{ issue.requested }}, tersedia {{ issue.available }}
                    </li>
                </ul>
            </div>
        </div>

        <div v-else-if="isDraft && order.stock_ok === false" class="banner banner--bad" role="alert">
            <AppIcon name="alert" :size="18" />
            <span>Stok saat ini tidak cukup untuk sebagian barang. Pesanan belum bisa disetujui.</span>
        </div>

        <dl class="kv" style="margin-bottom: 18px">
            <div><dt>Customer</dt><dd>{{ order.customer_name }}</dd></div>
            <div><dt>Telepon</dt><dd>{{ order.customer_phone }}</dd></div>
            <div><dt>Dipesan</dt><dd>{{ formatDateTime(order.created_at) }}</dd></div>
            <div><dt>Pembayaran</dt><dd>{{ order.payment_method_label }}</dd></div>
            <div v-if="order.cashier"><dt>Kasir</dt><dd>{{ order.cashier }}</dd></div>
            <div v-if="order.approved_at"><dt>Disetujui</dt><dd>{{ formatDateTime(order.approved_at) }}</dd></div>
            <div v-if="order.completed_at"><dt>Selesai</dt><dd>{{ formatDateTime(order.completed_at) }}</dd></div>
            <div v-if="order.cancelled_at"><dt>Dibatalkan</dt><dd>{{ formatDateTime(order.cancelled_at) }}<span v-if="order.cancelled_by" class="muted"> oleh {{ order.cancelled_by }}</span></dd></div>
            <div v-if="order.cancel_reason" style="grid-column: 1 / -1"><dt>Alasan Pembatalan</dt><dd>{{ order.cancel_reason }}</dd></div>
            <div v-if="order.notes" style="grid-column: 1 / -1"><dt>Catatan Customer</dt><dd>{{ order.notes }}</dd></div>
        </dl>

        <div class="table-wrap">
            <table class="table">
                <thead>
                    <tr>
                        <th>Barang</th>
                        <th class="num">Harga</th>
                        <th class="num">Jumlah</th>
                        <th v-if="showReturned" class="num">Diretur</th>
                        <th v-if="isDraft" class="num">Stok Saat Ini</th>
                        <th class="num">Subtotal</th>
                    </tr>
                </thead>
                <tbody>
                    <tr v-for="item in order.items" :key="item.id" :class="{ 'row-warn': item.stock_ok === false }">
                        <td>
                            <div class="product-cell">
                                <span class="thumb">
                                    <img v-if="item.image_url" :src="item.image_url" :alt="item.name" loading="lazy" />
                                    <AppIcon v-else name="image" />
                                </span>
                                <div>
                                    <strong>{{ item.name }}</strong>
                                    <div class="hint">{{ item.code }}</div>
                                    <span v-if="!item.product_active" class="badge badge-muted">Produk nonaktif</span>
                                </div>
                            </div>
                        </td>
                        <td class="num">{{ formatRupiah(item.unit_price) }}</td>
                        <td class="num">{{ formatNumber(item.quantity) }} {{ item.unit ?? '' }}</td>
                        <td v-if="showReturned" class="num">{{ formatNumber(item.returned_quantity) }}</td>
                        <td v-if="isDraft" class="num">
                            <span :class="item.stock_ok ? 'stock-ok' : 'stock-short'">{{ formatNumber(item.stock) }}</span>
                            <div v-if="item.stock_ok === false" class="hint stock-short">kurang {{ item.quantity - item.stock }}</div>
                        </td>
                        <td class="num">{{ formatRupiah(item.subtotal) }}</td>
                    </tr>
                </tbody>
            </table>
        </div>

        <div class="pay-panel">
            <div>
                <div class="muted">Total pembayaran ({{ formatNumber(order.total_quantity) }} barang)</div>
                <div class="pay-panel__total">{{ formatRupiah(order.total_amount) }}</div>
                <div v-if="order.returned_amount > 0" class="hint">Nilai retur: {{ formatRupiah(order.returned_amount) }}</div>
            </div>

            <div class="action-row">
                <template v-if="!readonly">
                    <button
                        v-if="order.can_approve"
                        class="btn btn-primary btn-lg"
                        type="button"
                        :disabled="busy || order.stock_ok === false"
                        @click="emit('approve')"
                    >
                        <AppIcon name="check-circle" :size="20" /> Setujui Pesanan
                    </button>
                    <button v-if="order.can_complete" class="btn btn-primary btn-lg" type="button" :disabled="busy" @click="emit('complete')">
                        <AppIcon name="wallet" :size="20" /> SELESAIKAN
                    </button>
                    <button v-if="order.can_cancel" class="btn btn-outline-danger btn-lg" type="button" :disabled="busy" @click="emit('cancel')">
                        <AppIcon name="x-circle" :size="20" /> Batalkan
                    </button>
                </template>

                <RouterLink
                    v-if="order.can_return"
                    class="btn btn-secondary btn-lg"
                    :to="{ name: 'kasir.returns', query: { order: order.order_number } }"
                >
                    <AppIcon name="refresh" :size="20" /> Retur Barang
                </RouterLink>
            </div>
        </div>

        <p v-if="!readonly && isFinal" class="hint" style="margin: 12px 0 0">
            Transaksi ini sudah final dan tidak dapat diproses ulang.
        </p>

        <template v-if="order.returns && order.returns.length">
            <h3 style="margin: 22px 0 12px">Riwayat Retur</h3>
            <div v-for="item in order.returns" :key="item.id" class="return-card">
                <strong>{{ item.return_number }}</strong>
                <span class="muted"> · {{ formatDateTime(item.created_at) }}<span v-if="item.user"> · {{ item.user }}</span></span>
                <div class="hint">Alasan: {{ item.reason }}</div>
                <ul>
                    <li v-for="line in item.items" :key="line.id">{{ line.quantity }} × {{ line.name }}</li>
                </ul>
                <strong>Nilai retur: {{ formatRupiah(item.total_amount) }}</strong>
            </div>
        </template>

        <h3 style="margin: 22px 0 12px">Riwayat Status</h3>
        <ul class="history">
            <li v-for="(history, index) in order.histories" :key="index">
                <strong>{{ history.to_label }}</strong>
                <span v-if="history.user" class="muted"> · {{ history.user }}</span>
                <small>{{ formatDateTime(history.created_at) }}</small>
                <small v-if="history.note">{{ history.note }}</small>
            </li>
        </ul>
    </div>
</template>
