<script setup>
import { computed, onMounted, reactive, ref } from 'vue';
import http from '@/lib/http';
import ConfirmDialog from '@/components/common/ConfirmDialog.vue';
import ColorField from '@/components/settings/ColorField.vue';
import { loadBranding, useBranding } from '@/composables/useBranding';
import { useToast } from '@/composables/useToast';
import { contrast, isHex } from '@/lib/colors';
import { errorMessage, validationErrors } from '@/lib/utils';

const KEYS = ['primary', 'secondary', 'danger', 'background', 'dark'];

const LABELS = {
    primary: 'Primary',
    secondary: 'Secondary',
    danger: 'Danger',
    background: 'Background',
    dark: 'Dark',
};

const HINTS = {
    primary: 'Tombol utama, menu aktif, indikator berhasil.',
    secondary: 'Highlight, badge informasi, elemen promosi.',
    danger: 'Pembatalan, peringatan, penghapusan, stok kritis.',
    background: 'Latar halaman dan area konten.',
    dark: 'Sidebar, header, judul, dan teks penting.',
};

const toast = useToast();
const branding = useBranding();

const loading = ref(true);
const saving = ref(false);
const errors = ref({});
const resetOpen = ref(false);

const saved = reactive({ primary: '#06D6A0', secondary: '#FFD166', danger: '#EF233C', background: '#F7FFF7', dark: '#26547C' });
const defaults = reactive({ primary: '#06D6A0', secondary: '#FFD166', danger: '#EF233C', background: '#F7FFF7', dark: '#26547C' });
const form = reactive({ primary: '#06D6A0', secondary: '#FFD166', danger: '#EF233C', background: '#F7FFF7', dark: '#26547C' });

const allValid = computed(() => KEYS.every((key) => isHex(form[key])));
const dirty = computed(() => KEYS.some((key) => form[key].toUpperCase() !== saved[key].toUpperCase()));
const atDefaults = computed(() => KEYS.every((key) => saved[key].toUpperCase() === defaults[key].toUpperCase()));

const blockers = computed(() => {
    const list = [];

    if (!allValid.value) {
        return list;
    }

    if (contrast(form.dark, '#FFFFFF') < 3) {
        list.push('Warna Dark terlalu terang: teks tidak terbaca di atas kartu putih.');
    }

    if (contrast(form.dark, form.background) < 3) {
        list.push('Kontras warna Dark terhadap Background terlalu rendah: teks tidak terbaca.');
    }

    return list;
});

const warnings = computed(() => {
    const list = [];

    if (!allValid.value) {
        return list;
    }

    if (contrast(form.dark, form.primary) < 3) {
        list.push('Teks pada tombol Primary kurang kontras (di bawah 3:1).');
    }

    if (contrast(form.dark, form.secondary) < 3) {
        list.push('Teks pada elemen Secondary kurang kontras (di bawah 3:1).');
    }

    if (contrast('#FFFFFF', form.danger) < 3) {
        list.push('Teks putih pada tombol Danger kurang kontras (di bawah 3:1).');
    }

    return list;
});

const previewStyle = computed(() => {
    const pick = (key) => (isHex(form[key]) ? form[key] : saved[key]);

    return {
        '--primary': pick('primary'),
        '--success': pick('primary'),
        '--secondary': pick('secondary'),
        '--warning': pick('secondary'),
        '--danger': pick('danger'),
        '--background': pick('background'),
        '--dark': pick('dark'),
        '--text-primary': pick('dark'),
    };
});

function apply(payload) {
    Object.assign(saved, payload.data);
    Object.assign(defaults, payload.defaults);
    Object.assign(form, payload.data);
}

onMounted(async () => {
    try {
        const { data } = await http.get('/settings/colors');
        apply(data);
    } catch (error) {
        toast.error(errorMessage(error, 'Gagal memuat pengaturan warna.'));
    } finally {
        loading.value = false;
    }
});

function revert() {
    Object.assign(form, saved);
    errors.value = {};
}

async function save() {
    saving.value = true;
    errors.value = {};

    try {
        const { data } = await http.put('/settings/colors', { ...form });

        apply(data);
        await loadBranding(true);
        toast.success(data.message);
    } catch (error) {
        errors.value = validationErrors(error);
        toast.error(errorMessage(error));
    } finally {
        saving.value = false;
    }
}

async function resetToDefault() {
    saving.value = true;

    try {
        const { data } = await http.post('/settings/colors/reset');

        apply(data);
        await loadBranding(true);
        toast.success(data.message);
    } catch (error) {
        toast.error(errorMessage(error));
    } finally {
        saving.value = false;
        resetOpen.value = false;
    }
}
</script>

<template>
    <section>
        <div class="page-header">
            <div>
                <h1>Pengaturan Warna Website</h1>
                <p>Warna berlaku untuk seluruh aplikasi, termasuk storefront customer. Periksa pratinjau sebelum menyimpan.</p>
            </div>
        </div>

        <div v-if="loading" class="card loading-line">Memuat pengaturan...</div>

        <div v-else class="two-col">
            <form class="card" novalidate @submit.prevent="save">
                <h3>Warna</h3>

                <div class="color-grid">
                    <ColorField
                        v-for="key in KEYS"
                        :key="key"
                        v-model="form[key]"
                        :label="LABELS[key]"
                        :hint="HINTS[key]"
                        :error="errors[key] ?? ''"
                    />
                </div>

                <div v-if="blockers.length" class="alert alert-danger" role="alert">
                    <ul style="margin: 0">
                        <li v-for="item in blockers" :key="item">{{ item }}</li>
                    </ul>
                </div>

                <div v-if="warnings.length" class="banner banner--warn">
                    <div>
                        <strong>Peringatan keterbacaan (masih bisa disimpan):</strong>
                        <ul>
                            <li v-for="item in warnings" :key="item">{{ item }}</li>
                        </ul>
                    </div>
                </div>

                <div class="action-row">
                    <button class="btn btn-primary" type="submit" :disabled="saving || !dirty || !allValid || blockers.length > 0">
                        {{ saving ? 'Menyimpan...' : 'Simpan Perubahan' }}
                    </button>
                    <button class="btn btn-outline" type="button" :disabled="saving || !dirty" @click="revert">Batalkan Perubahan</button>
                    <button class="btn btn-outline-danger" type="button" :disabled="saving || atDefaults" @click="resetOpen = true">
                        Reset ke Warna Default
                    </button>
                </div>
            </form>

            <div class="card">
                <h3>Pratinjau Langsung</h3>
                <p class="hint" style="margin-top: 0">Pratinjau tidak mengubah halaman lain sampai Anda menyimpan.</p>

                <div class="color-preview" :style="previewStyle">
                    <div class="mock">
                        <div class="mock__side">
                            <strong>{{ branding.company.store_name }}</strong>
                            <div class="mock__item mock__item--active">Dashboard</div>
                            <div class="mock__item">Produk</div>
                            <div class="mock__item">Laporan</div>
                        </div>
                        <div class="mock__main">
                            <h4 style="margin: 0">Judul Halaman</h4>
                            <div class="card" style="padding: 12px">
                                <p style="margin: 0 0 10px">Contoh kartu dengan teks penting.</p>
                                <div style="display: flex; flex-wrap: wrap; gap: 8px">
                                    <button class="btn btn-primary btn-sm" type="button">Simpan</button>
                                    <button class="btn btn-secondary btn-sm" type="button">Promo</button>
                                    <button class="btn btn-danger btn-sm" type="button">Hapus</button>
                                </div>
                                <div style="display: flex; flex-wrap: wrap; gap: 6px; margin-top: 10px">
                                    <span class="badge badge-success">Tersedia</span>
                                    <span class="badge badge-info">Menipis</span>
                                    <span class="badge badge-danger">Habis</span>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <ConfirmDialog
            v-model="resetOpen"
            title="Reset ke Warna Default"
            message="Kembalikan seluruh warna ke palet default BroSeven! Shop? Perubahan langsung berlaku untuk seluruh aplikasi."
            confirm-text="Ya, Reset"
            danger
            :loading="saving"
            @confirm="resetToDefault"
        />
    </section>
</template>
