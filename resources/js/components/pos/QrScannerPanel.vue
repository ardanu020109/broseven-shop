<script setup>
import { computed, onBeforeUnmount, ref } from 'vue';
import AppIcon from '@/components/common/AppIcon.vue';

const emit = defineEmits(['scan']);

const video = ref(null);
const state = ref('idle');
const message = ref('');
const cameras = ref([]);
const cameraIndex = ref(0);
const flashAvailable = ref(false);
const flashOn = ref(false);

let scanner = null;
let lastCode = '';
let lastAt = 0;

const live = computed(() => state.value === 'running' || state.value === 'starting');

function describeError(error) {
    const name = error?.name ?? '';
    const text = String(error?.message ?? error ?? '');

    if (name === 'NotAllowedError' || /permission|denied/i.test(text)) {
        return 'Izin kamera ditolak. Izinkan akses kamera di pengaturan browser, lalu coba lagi.';
    }

    if (name === 'NotFoundError' || /no camera/i.test(text)) {
        return 'Kamera tidak ditemukan. Gunakan input manual atau scanner USB.';
    }

    if (name === 'NotReadableError') {
        return 'Kamera sedang dipakai aplikasi lain.';
    }

    return 'Kamera gagal diaktifkan. Gunakan input manual sebagai cadangan.';
}

function onDecode(result) {
    const code = String(result?.data ?? '').trim();

    if (!code) {
        return;
    }

    const now = Date.now();

    if (code === lastCode && now - lastAt < 3000) {
        return;
    }

    lastCode = code;
    lastAt = now;
    navigator.vibrate?.(60);
    emit('scan', code);
}

function release() {
    if (scanner) {
        scanner.destroy();
        scanner = null;
    }

    flashOn.value = false;
    flashAvailable.value = false;
    cameras.value = [];
    cameraIndex.value = 0;
}

async function stop() {
    release();
    state.value = 'idle';
    message.value = '';
}

async function start() {
    if (live.value) {
        return;
    }

    if (!window.isSecureContext) {
        state.value = 'error';
        message.value = 'Kamera hanya bisa dipakai lewat HTTPS atau localhost. Gunakan input manual atau scanner USB.';
        return;
    }

    state.value = 'starting';
    message.value = '';

    try {
        const { default: QrScanner } = await import('qr-scanner');

        if (!(await QrScanner.hasCamera())) {
            throw Object.assign(new Error('no camera'), { name: 'NotFoundError' });
        }

        scanner = new QrScanner(video.value, onDecode, {
            preferredCamera: 'environment',
            maxScansPerSecond: 10,
            highlightScanRegion: true,
            highlightCodeOutline: true,
            returnDetailedScanResult: true,
            onDecodeError: () => {},
        });

        await scanner.start();
        state.value = 'running';
        cameras.value = await QrScanner.listCameras(true);
        flashAvailable.value = await scanner.hasFlash();
    } catch (error) {
        release();
        state.value = 'error';
        message.value = describeError(error);
    }
}

async function switchCamera() {
    if (!scanner || cameras.value.length < 2) {
        return;
    }

    cameraIndex.value = (cameraIndex.value + 1) % cameras.value.length;
    await scanner.setCamera(cameras.value[cameraIndex.value].id);
    flashOn.value = false;
    flashAvailable.value = await scanner.hasFlash();
}

async function toggleFlash() {
    if (!scanner) {
        return;
    }

    await scanner.toggleFlash();
    flashOn.value = scanner.isFlashOn();
}

onBeforeUnmount(release);

defineExpose({ start, stop });
</script>

<template>
    <div class="scanner">
        <div class="scanner__view">
            <video v-show="live" ref="video" class="scanner__video" muted playsinline></video>

            <div v-if="state === 'starting'" class="scanner__overlay">Mengaktifkan kamera...</div>

            <div v-if="!live" class="scanner__placeholder">
                <AppIcon :name="state === 'error' ? 'camera-off' : 'camera'" :size="40" />
                <p v-if="state === 'error'">{{ message }}</p>
                <p v-else>Arahkan kamera ke QR Code pesanan customer.</p>
            </div>
        </div>

        <div class="scanner__bar">
            <button v-if="!live" class="btn btn-primary" type="button" @click="start">
                <AppIcon name="camera" :size="18" /> Aktifkan Kamera
            </button>

            <template v-else>
                <button class="btn btn-outline" type="button" @click="stop">
                    <AppIcon name="camera-off" :size="18" /> Matikan Kamera
                </button>
                <button v-if="cameras.length > 1" class="btn btn-outline" type="button" @click="switchCamera">
                    <AppIcon name="refresh" :size="18" /> Ganti Kamera
                </button>
                <button v-if="flashAvailable" class="btn btn-outline" type="button" @click="toggleFlash">
                    <AppIcon name="zap" :size="18" /> Senter {{ flashOn ? 'On' : 'Off' }}
                </button>
            </template>
        </div>
    </div>
</template>
