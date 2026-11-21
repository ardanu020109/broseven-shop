<script setup>
import { computed, reactive, watch } from 'vue';
import { isValidPeriod, pad } from '@/lib/period';

const props = defineProps({
    modelValue: { type: Object, required: true },
});

const emit = defineEmits(['update:modelValue']);

const months = ['Januari', 'Februari', 'Maret', 'April', 'Mei', 'Juni', 'Juli', 'Agustus', 'September', 'Oktober', 'November', 'Desember'];

const local = reactive({ ...props.modelValue });

const monthYear = computed({
    get: () => Number(local.month.slice(0, 4)) || new Date().getFullYear(),
    set: (value) => {
        if (Number.isInteger(value) && value >= 2000 && value <= 2100) {
            local.month = `${value}-${local.month.slice(5, 7) || '01'}`;
        }
    },
});

const monthNumber = computed({
    get: () => Number(local.month.slice(5, 7)) || 1,
    set: (value) => {
        local.month = `${local.month.slice(0, 4)}-${pad(value)}`;
    },
});

watch(
    local,
    () => {
        if (isValidPeriod(local)) {
            emit('update:modelValue', { ...local });
        }
    },
    { deep: true },
);
</script>

<template>
    <div class="period">
        <select v-model="local.type" class="input" aria-label="Jenis periode">
            <option value="day">Harian</option>
            <option value="month">Bulanan</option>
            <option value="year">Tahunan</option>
            <option value="range">Rentang tanggal</option>
        </select>

        <input v-if="local.type === 'day'" v-model="local.date" class="input" type="date" aria-label="Tanggal" />

        <template v-else-if="local.type === 'month'">
            <select v-model.number="monthNumber" class="input" aria-label="Bulan">
                <option v-for="(name, index) in months" :key="name" :value="index + 1">{{ name }}</option>
            </select>
            <input v-model.number="monthYear" class="input period__year" type="number" min="2000" max="2100" aria-label="Tahun" />
        </template>

        <input v-else-if="local.type === 'year'" v-model.number="local.year" class="input period__year" type="number" min="2000" max="2100" aria-label="Tahun" />

        <template v-else>
            <input v-model="local.from" class="input" type="date" aria-label="Dari tanggal" />
            <span class="muted">s/d</span>
            <input v-model="local.to" class="input" type="date" aria-label="Sampai tanggal" />
        </template>
    </div>
</template>
