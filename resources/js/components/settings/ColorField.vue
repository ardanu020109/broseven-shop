<script setup>
import { computed } from 'vue';
import { isHex, normalizeHex } from '@/lib/colors';

const props = defineProps({
    modelValue: { type: String, required: true },
    label: { type: String, required: true },
    hint: { type: String, default: '' },
    error: { type: String, default: '' },
});

const emit = defineEmits(['update:modelValue']);

const valid = computed(() => isHex(props.modelValue));
const pickerValue = computed(() => (valid.value ? props.modelValue.toLowerCase() : '#000000'));

function onText(event) {
    emit('update:modelValue', normalizeHex(event.target.value).slice(0, 7));
}

function onPicker(event) {
    emit('update:modelValue', normalizeHex(event.target.value));
}
</script>

<template>
    <div class="form-group color-field">
        <span class="form-label">{{ label }}</span>
        <div class="color-field__row">
            <input type="color" :value="pickerValue" :aria-label="`Pilih warna ${label}`" @input="onPicker" />
            <input
                class="input"
                :class="{ 'is-invalid': !valid || error }"
                type="text"
                maxlength="7"
                spellcheck="false"
                :value="modelValue"
                :aria-label="`Kode HEX ${label}`"
                @input="onText"
            />
        </div>
        <span v-if="error" class="form-error">{{ error }}</span>
        <span v-else-if="!valid" class="form-error">Gunakan format HEX 6 digit, contoh #06D6A0.</span>
        <span v-else-if="hint" class="hint">{{ hint }}</span>
    </div>
</template>
