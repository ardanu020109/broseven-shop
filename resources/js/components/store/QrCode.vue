<script setup>
import { ref, watch } from 'vue';
import QRCode from 'qrcode';

const props = defineProps({
    value: { type: String, required: true },
    size: { type: Number, default: 220 },
});

const source = ref('');
const failed = ref(false);

watch(
    () => props.value,
    async (value) => {
        failed.value = false;

        try {
            source.value = await QRCode.toDataURL(value, {
                width: 640,
                margin: 1,
                errorCorrectionLevel: 'M',
                color: { dark: '#111111', light: '#FFFFFF' },
            });
        } catch {
            source.value = '';
            failed.value = true;
        }
    },
    { immediate: true },
);
</script>

<template>
    <div class="qr" :class="{ 'qr--empty': !source }" :style="{ width: `${size}px`, maxWidth: '100%' }">
        <img v-if="source" :src="source" alt="QR Code pesanan" />
        <span v-else-if="failed">QR gagal dibuat</span>
        <span v-else>Memuat QR...</span>
    </div>
</template>
