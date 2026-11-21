<script setup>
import AppIcon from '@/components/common/AppIcon.vue';

const props = defineProps({
    modelValue: { type: Number, required: true },
    min: { type: Number, default: 1 },
    max: { type: Number, default: 999 },
    disabled: { type: Boolean, default: false },
});

const emit = defineEmits(['update:modelValue']);

function clamp(value) {
    const number = Math.trunc(Number(value)) || props.min;

    return Math.min(props.max, Math.max(props.min, number));
}

function set(value) {
    emit('update:modelValue', clamp(value));
}

function onChange(event) {
    const next = clamp(event.target.value);

    event.target.value = next;
    emit('update:modelValue', next);
}
</script>

<template>
    <div class="stepper">
        <button type="button" aria-label="Kurangi" :disabled="disabled || modelValue <= min" @click="set(modelValue - 1)">
            <AppIcon name="minus" :size="16" />
        </button>
        <input
            type="number"
            inputmode="numeric"
            aria-label="Jumlah"
            :value="modelValue"
            :min="min"
            :max="max"
            :disabled="disabled"
            @change="onChange"
        />
        <button type="button" aria-label="Tambah" :disabled="disabled || modelValue >= max" @click="set(modelValue + 1)">
            <AppIcon name="plus" :size="16" />
        </button>
    </div>
</template>
