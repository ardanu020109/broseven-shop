<script setup>
import { ref, watch } from 'vue';
import http from '@/lib/http';
import AppModal from '@/components/common/AppModal.vue';
import StatusBadge from '@/components/common/StatusBadge.vue';
import { useToast } from '@/composables/useToast';
import { errorMessage, formatDateTime, formatNumber, formatRupiah } from '@/lib/utils';

const props = defineProps({
    modelValue: { type: Boolean, default: false },
    userId: { type: Number, default: null },
});

const emit = defineEmits(['update:modelValue']);

const toast = useToast();
const loading = ref(false);
const user = ref(null);
const stats = ref(null);
const activities = ref([]);

watch(
    () => [props.modelValue, props.userId],
    async ([open, id]) => {
        if (!open || !id) {
            return;
        }

        loading.value = true;
        user.value = null;

        try {
            const { data } = await http.get(`/users/${id}`);
            user.value = data.data;
            stats.value = data.stats;
            activities.value = data.activities;
        } catch (error) {
            toast.error(errorMessage(error));
            emit('update:modelValue', false);
        } finally {
            loading.value = false;
        }
    },
    { immediate: true },
);
</script>

<template>
    <AppModal :model-value="modelValue" title="Detail Pengguna" width="640px" @update:model-value="emit('update:modelValue', $event)">
        <p v-if="loading" class="loading-line" style="margin: 0">Memuat detail...</p>

        <template v-else-if="user">
            <div class="user-cell" style="margin-bottom: 16px">
                <span class="avatar avatar--lg">
                    <img v-if="user.avatar_url" :src="user.avatar_url" :alt="user.name" />
                    <template v-else>{{ user.name.slice(0, 1).toUpperCase() }}</template>
                </span>
                <div>
                    <h3 style="margin: 0">{{ user.name }}</h3>
                    <span class="muted">@{{ user.username }}</span>
                    <div style="margin-top: 6px; display: flex; gap: 6px; flex-wrap: wrap">
                        <span class="badge badge-info">{{ user.role_label }}</span>
                        <StatusBadge :value="user.is_active" />
                        <span v-if="user.is_primary" class="badge badge-dark">Akun Utama</span>
                    </div>
                </div>
            </div>

            <dl class="kv" style="margin-bottom: 16px">
                <div><dt>Email</dt><dd>{{ user.email }}</dd></div>
                <div><dt>Login terakhir</dt><dd>{{ formatDateTime(user.last_login_at) }}</dd></div>
                <div><dt>Dibuat</dt><dd>{{ formatDateTime(user.created_at) }}</dd></div>
                <div><dt>Diperbarui</dt><dd>{{ formatDateTime(user.updated_at) }}</dd></div>
            </dl>

            <div v-if="stats" class="mini-stats">
                <div><small>Transaksi selesai</small><strong>{{ formatNumber(stats.completed) }}</strong></div>
                <div><small>Total pendapatan</small><strong>{{ formatRupiah(stats.revenue) }}</strong></div>
                <div><small>Dibatalkan</small><strong>{{ formatNumber(stats.cancelled) }}</strong></div>
                <div><small>Menunggu bayar</small><strong>{{ formatNumber(stats.waiting) }}</strong></div>
                <div><small>Retur diproses</small><strong>{{ formatNumber(stats.returns) }}</strong></div>
            </div>

            <h4 style="margin-bottom: 8px">Aktivitas Terakhir</h4>
            <p v-if="activities.length === 0" class="muted" style="margin: 0">Belum ada aktivitas tercatat.</p>
            <ul v-else class="activity-list">
                <li v-for="item in activities" :key="item.id">
                    <strong>{{ item.action }}</strong>
                    <span v-if="item.description"> · {{ item.description }}</span>
                    <small>{{ formatDateTime(item.created_at) }}<span v-if="item.ip_address"> · {{ item.ip_address }}</span></small>
                </li>
            </ul>
        </template>
    </AppModal>
</template>
