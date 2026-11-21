<script setup>
import { onBeforeUnmount, onMounted, reactive, ref } from 'vue';
import http from '@/lib/http';
import AppIcon from '@/components/common/AppIcon.vue';
import { loadBranding } from '@/composables/useBranding';
import { useToast } from '@/composables/useToast';
import { errorMessage, validationErrors } from '@/lib/utils';

const LOGO_TYPES = ['image/jpeg', 'image/png', 'image/webp'];
const LOGO_MAX = 2 * 1024 * 1024;
const FAVICON_MAX = 512 * 1024;

const toast = useToast();

const loading = ref(true);
const saving = ref(false);
const errors = ref({});
const form = reactive({ store_name: '', address: '', phone: '', email: '', description: '', footer_text: '', draft_expiry_hours: 24 });
const current = reactive({ logo_url: null, favicon_url: null });
const files = reactive({ logo: null, favicon: null });
const previews = reactive({ logo: null, favicon: null });
const removes = reactive({ logo: false, favicon: false });
const inputs = reactive({ logo: null, favicon: null });

function shown(kind) {
    return previews[kind] || (removes[kind] ? null : current[`${kind}_url`]);
}

function revoke(kind) {
    if (previews[kind]) {
        URL.revokeObjectURL(previews[kind]);
    }

    previews[kind] = null;
}

function resetFiles() {
    for (const kind of ['logo', 'favicon']) {
        revoke(kind);
        files[kind] = null;
        removes[kind] = false;
    }
}

function fill(data) {
    form.store_name = data.store_name ?? '';
    form.address = data.address ?? '';
    form.phone = data.phone ?? '';
    form.email = data.email ?? '';
    form.description = data.description ?? '';
    form.footer_text = data.footer_text ?? '';
    form.draft_expiry_hours = data.draft_expiry_hours ?? 24;
    current.logo_url = data.logo_url;
    current.favicon_url = data.favicon_url;
    resetFiles();
}

onMounted(async () => {
    try {
        const { data } = await http.get('/settings/company');
        fill(data.data);
    } catch (error) {
        toast.error(errorMessage(error, 'Gagal memuat pengaturan.'));
    } finally {
        loading.value = false;
    }
});

onBeforeUnmount(resetFiles);

function pick(kind, event) {
    const file = event.target.files?.[0];
    event.target.value = '';

    if (!file) {
        return;
    }

    const isIco = /\.ico$/i.test(file.name);
    const typeOk = kind === 'logo' ? LOGO_TYPES.includes(file.type) : isIco || [...LOGO_TYPES, 'image/x-icon'].includes(file.type);
    const max = kind === 'logo' ? LOGO_MAX : FAVICON_MAX;
    const label = kind === 'logo' ? 'Logo' : 'Favicon';

    if (!typeOk) {
        errors.value = { ...errors.value, [kind]: `${label} harus berformat ${kind === 'logo' ? 'JPG, PNG, atau WEBP' : 'PNG, ICO, JPG, atau WEBP'}.` };
        return;
    }

    if (file.size > max) {
        errors.value = { ...errors.value, [kind]: `Ukuran ${label.toLowerCase()} maksimal ${kind === 'logo' ? '2 MB' : '512 KB'}.` };
        return;
    }

    const { [kind]: removed, ...rest } = errors.value;
    errors.value = rest;

    revoke(kind);
    files[kind] = file;
    previews[kind] = isIco ? null : URL.createObjectURL(file);
    removes[kind] = false;
}

function drop(kind) {
    revoke(kind);
    files[kind] = null;
    removes[kind] = Boolean(current[`${kind}_url`]);
}

async function save() {
    saving.value = true;
    errors.value = {};

    const body = new FormData();

    for (const [key, value] of Object.entries(form)) {
        body.append(key, String(value).trim());
    }

    for (const kind of ['logo', 'favicon']) {
        if (files[kind]) {
            body.append(kind, files[kind]);
        } else if (removes[kind]) {
            body.append(`remove_${kind}`, '1');
        }
    }

    try {
        const { data } = await http.post('/settings/company', body);

        fill(data.data);
        await loadBranding(true);
        toast.success(data.message);
    } catch (error) {
        errors.value = validationErrors(error);
        toast.error(errorMessage(error));
    } finally {
        saving.value = false;
    }
}
</script>

<template>
    <section>
        <div class="page-header">
            <div>
                <h1>Profil Perusahaan</h1>
                <p>Identitas toko yang dipakai di storefront, dashboard, laporan, dan invoice.</p>
            </div>
        </div>

        <div v-if="loading" class="card loading-line">Memuat pengaturan...</div>

        <form v-else class="form-layout" novalidate @submit.prevent="save">
            <div class="card">
                <h3>Informasi Toko</h3>

                <div class="form-group">
                    <label class="form-label" for="c-name">Nama Toko</label>
                    <input id="c-name" v-model="form.store_name" class="input" :class="{ 'is-invalid': errors.store_name }" type="text" maxlength="100" />
                    <span v-if="errors.store_name" class="form-error">{{ errors.store_name }}</span>
                </div>

                <div class="form-group">
                    <label class="form-label" for="c-address">Alamat Toko</label>
                    <textarea id="c-address" v-model="form.address" class="input" :class="{ 'is-invalid': errors.address }" rows="2" maxlength="500"></textarea>
                    <span v-if="errors.address" class="form-error">{{ errors.address }}</span>
                </div>

                <div class="form-row form-row--2">
                    <div class="form-group">
                        <label class="form-label" for="c-phone">Nomor Telepon</label>
                        <input id="c-phone" v-model="form.phone" class="input" :class="{ 'is-invalid': errors.phone }" type="text" maxlength="30" />
                        <span v-if="errors.phone" class="form-error">{{ errors.phone }}</span>
                    </div>
                    <div class="form-group">
                        <label class="form-label" for="c-email">Email</label>
                        <input id="c-email" v-model="form.email" class="input" :class="{ 'is-invalid': errors.email }" type="email" maxlength="255" />
                        <span v-if="errors.email" class="form-error">{{ errors.email }}</span>
                    </div>
                </div>

                <div class="form-group">
                    <label class="form-label" for="c-desc">Deskripsi Toko</label>
                    <textarea id="c-desc" v-model="form.description" class="input" :class="{ 'is-invalid': errors.description }" rows="3" maxlength="1000"></textarea>
                    <span v-if="errors.description" class="form-error">{{ errors.description }}</span>
                </div>

                <div class="form-group">
                    <label class="form-label" for="c-footer">Informasi Footer</label>
                    <input id="c-footer" v-model="form.footer_text" class="input" :class="{ 'is-invalid': errors.footer_text }" type="text" maxlength="300" />
                    <span v-if="errors.footer_text" class="form-error">{{ errors.footer_text }}</span>
                </div>

                <div class="form-group">
                    <label class="form-label" for="c-expiry">Hapus otomatis pesanan draft setelah (jam)</label>
                    <input id="c-expiry" v-model.number="form.draft_expiry_hours" class="input" :class="{ 'is-invalid': errors.draft_expiry_hours }" type="number" min="0" max="720" step="1" />
                    <span v-if="errors.draft_expiry_hours" class="form-error">{{ errors.draft_expiry_hours }}</span>
                    <span v-else class="hint">Pesanan DRAFT yang belum divalidasi kasir dihapus permanen setelah waktu ini. Isi 0 untuk menonaktifkan. Maksimal 720 jam (30 hari). Butuh penjadwal Laravel yang aktif (lihat README).</span>
                </div>
            </div>

            <div class="card">
                <h3>Logo &amp; Favicon</h3>

                <div class="uploader">
                    <span class="form-label">Logo Toko</span>
                    <div class="uploader__preview uploader__preview--wide">
                        <img v-if="shown('logo')" :src="shown('logo')" alt="Logo toko" />
                        <AppIcon v-else name="image" :size="36" />
                    </div>
                    <div class="uploader__actions">
                        <button class="btn btn-outline btn-sm" type="button" @click="inputs.logo.click()">
                            <AppIcon name="upload" :size="16" /> Pilih Logo
                        </button>
                        <button v-if="shown('logo')" class="btn btn-outline-danger btn-sm" type="button" @click="drop('logo')">
                            <AppIcon name="trash" :size="16" /> Hapus
                        </button>
                    </div>
                    <input :ref="(el) => (inputs.logo = el)" type="file" accept="image/jpeg,image/png,image/webp" hidden @change="pick('logo', $event)" />
                    <span class="hint">JPG, PNG, atau WEBP. Maksimal 2 MB.</span>
                    <span v-if="errors.logo" class="form-error">{{ errors.logo }}</span>
                </div>

                <hr style="border: 0; border-top: 1px solid var(--border-color); margin: 20px 0" />

                <div class="uploader">
                    <span class="form-label">Favicon</span>
                    <div class="uploader__preview uploader__preview--icon">
                        <img v-if="shown('favicon')" :src="shown('favicon')" alt="Favicon" />
                        <AppIcon v-else name="image" :size="28" />
                    </div>
                    <div class="uploader__actions">
                        <button class="btn btn-outline btn-sm" type="button" @click="inputs.favicon.click()">
                            <AppIcon name="upload" :size="16" /> Pilih Favicon
                        </button>
                        <button v-if="shown('favicon') || files.favicon" class="btn btn-outline-danger btn-sm" type="button" @click="drop('favicon')">
                            <AppIcon name="trash" :size="16" /> Hapus
                        </button>
                    </div>
                    <input :ref="(el) => (inputs.favicon = el)" type="file" accept=".ico,image/png,image/x-icon,image/vnd.microsoft.icon,image/jpeg,image/webp" hidden @change="pick('favicon', $event)" />
                    <span class="hint">PNG, ICO, JPG, atau WEBP. Maksimal 512 KB.</span>
                    <span v-if="errors.favicon" class="form-error">{{ errors.favicon }}</span>
                </div>

                <button class="btn btn-primary btn-block" type="submit" :disabled="saving" style="margin-top: 22px">
                    {{ saving ? 'Menyimpan...' : 'Simpan Perubahan' }}
                </button>
            </div>
        </form>
    </section>
</template>
