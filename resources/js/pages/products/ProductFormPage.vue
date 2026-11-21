<script setup>
import { computed, onBeforeUnmount, onMounted, reactive, ref, watch } from 'vue';
import { useRoute, useRouter } from 'vue-router';
import http from '@/lib/http';
import AppIcon from '@/components/common/AppIcon.vue';
import SearchSelect from '@/components/common/SearchSelect.vue';
import { useToast } from '@/composables/useToast';
import { errorMessage, formatNumber, validationErrors } from '@/lib/utils';

const route = useRoute();
const router = useRouter();
const toast = useToast();

const ALLOWED_TYPES = ['image/jpeg', 'image/png', 'image/webp'];
const MAX_IMAGE_BYTES = 2 * 1024 * 1024;

const productId = computed(() => (route.params.id ? Number(route.params.id) : null));
const isEdit = computed(() => productId.value !== null);

const lookups = reactive({ categories: [], product_types: [], brands: [], units: [] });
const product = ref(null);
const loading = ref(true);
const saving = ref(false);
const errors = ref({});
const fileInput = ref(null);
const imageFile = ref(null);
const imagePreview = ref(null);
const removeImage = ref(false);

const form = reactive({
    barcode: '',
    name: '',
    category_id: null,
    product_type_id: null,
    brand_id: null,
    unit_id: null,
    purchase_price: '',
    selling_price: '',
    stock: 0,
    min_stock: 0,
    description: '',
    is_active: true,
});

const currentImage = computed(() => {
    if (imagePreview.value) {
        return imagePreview.value;
    }

    return removeImage.value ? null : product.value?.image_url ?? null;
});

function optionsFor(list, currentId) {
    return list.filter((option) => option.is_active || option.id === currentId);
}

const categoryOptions = computed(() => optionsFor(lookups.categories, product.value?.category_id));
const typeOptions = computed(() => optionsFor(lookups.product_types, product.value?.product_type_id));
const brandOptions = computed(() => optionsFor(lookups.brands, product.value?.brand_id));
const unitOptions = computed(() => optionsFor(lookups.units, product.value?.unit_id));

function revokePreview() {
    if (imagePreview.value) {
        URL.revokeObjectURL(imagePreview.value);
    }

    imagePreview.value = null;
}

function resetForm() {
    form.barcode = '';
    form.name = '';
    form.category_id = null;
    form.product_type_id = null;
    form.brand_id = null;
    form.unit_id = null;
    form.purchase_price = '';
    form.selling_price = '';
    form.stock = 0;
    form.min_stock = 0;
    form.description = '';
    form.is_active = true;
    imageFile.value = null;
    removeImage.value = false;
    revokePreview();
    errors.value = {};
    product.value = null;
}

async function init() {
    loading.value = true;
    resetForm();

    try {
        const requests = [http.get('/lookups')];

        if (isEdit.value) {
            requests.push(http.get(`/products/${productId.value}`));
        }

        const [lookupResponse, productResponse] = await Promise.all(requests);
        Object.assign(lookups, lookupResponse.data);

        if (productResponse) {
            const data = productResponse.data.data;
            product.value = data;
            form.barcode = data.barcode ?? '';
            form.name = data.name;
            form.category_id = data.category_id;
            form.product_type_id = data.product_type_id;
            form.brand_id = data.brand_id;
            form.unit_id = data.unit_id;
            form.purchase_price = data.purchase_price ?? '';
            form.selling_price = data.selling_price;
            form.stock = data.stock;
            form.min_stock = data.min_stock;
            form.description = data.description ?? '';
            form.is_active = data.is_active;
        }
    } catch (error) {
        toast.error(errorMessage(error, 'Gagal memuat data.'));
        router.replace('/admin-master/products');
    } finally {
        loading.value = false;
    }
}

onMounted(init);
watch(productId, init);
onBeforeUnmount(revokePreview);

function pickFile(event) {
    const file = event.target.files?.[0];
    event.target.value = '';

    if (!file) {
        return;
    }

    if (!ALLOWED_TYPES.includes(file.type)) {
        errors.value = { ...errors.value, image: 'Foto harus berformat JPG, PNG, atau WEBP.' };
        return;
    }

    if (file.size > MAX_IMAGE_BYTES) {
        errors.value = { ...errors.value, image: 'Ukuran foto maksimal 2 MB.' };
        return;
    }

    const { image, ...rest } = errors.value;
    errors.value = rest;

    revokePreview();
    imageFile.value = file;
    imagePreview.value = URL.createObjectURL(file);
    removeImage.value = false;
}

function clearImage() {
    revokePreview();
    imageFile.value = null;
    removeImage.value = isEdit.value && Boolean(product.value?.image_url);
}

function clientValidate() {
    const found = {};

    if (form.name.trim() === '') {
        found.name = 'Nama barang wajib diisi.';
    }

    if (form.selling_price === '' || form.selling_price === null || Number(form.selling_price) < 0) {
        found.selling_price = 'Harga jual wajib diisi dan tidak boleh negatif.';
    }

    if (!isEdit.value && (form.stock === '' || form.stock === null || Number(form.stock) < 0)) {
        found.stock = 'Stok awal wajib diisi dan tidak boleh negatif.';
    }

    return found;
}

async function submit() {
    const found = clientValidate();

    if (Object.keys(found).length > 0) {
        errors.value = found;
        toast.error('Periksa kembali isian Anda.');
        return;
    }

    saving.value = true;
    errors.value = {};

    const body = new FormData();
    body.append('name', form.name.trim());
    body.append('barcode', form.barcode.trim());

    for (const key of ['category_id', 'product_type_id', 'brand_id', 'unit_id']) {
        body.append(key, form[key] ?? '');
    }

    body.append('purchase_price', form.purchase_price ?? '');
    body.append('selling_price', form.selling_price ?? '');
    body.append('min_stock', form.min_stock ?? '');
    body.append('description', form.description.trim());
    body.append('is_active', form.is_active ? '1' : '0');

    if (!isEdit.value) {
        body.append('stock', form.stock ?? '');
    }

    if (imageFile.value) {
        body.append('image', imageFile.value);
    } else if (removeImage.value) {
        body.append('remove_image', '1');
    }

    if (isEdit.value) {
        body.append('_method', 'PUT');
    }

    try {
        const { data } = await http.post(isEdit.value ? `/products/${productId.value}` : '/products', body);
        toast.success(data.message);
        router.push(`/admin-master/products/${data.data.id}`);
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
                <h1>{{ isEdit ? 'Edit Barang' : 'Tambah Barang' }}</h1>
                <p>{{ isEdit ? product?.code : 'Kode barang dibuat otomatis setelah disimpan.' }}</p>
            </div>
            <div class="page-actions">
                <button class="btn btn-outline" type="button" @click="router.push('/admin-master/products')">
                    <AppIcon name="arrow-left" :size="18" /> Kembali
                </button>
            </div>
        </div>

        <div v-if="loading" class="card loading-line">Memuat data...</div>

        <form v-else class="form-layout" novalidate @submit.prevent="submit">
            <div class="card">
                <h3>Informasi Barang</h3>

                <div class="form-group">
                    <label class="form-label" for="p-name">Nama Barang</label>
                    <input id="p-name" v-model="form.name" class="input" :class="{ 'is-invalid': errors.name }" type="text" maxlength="255" />
                    <span v-if="errors.name" class="form-error">{{ errors.name }}</span>
                </div>

                <div class="form-row form-row--2">
                    <div class="form-group">
                        <label class="form-label">Kode Barang</label>
                        <input class="input" type="text" :value="isEdit ? product?.code : 'Otomatis'" disabled />
                    </div>
                    <div class="form-group">
                        <label class="form-label" for="p-barcode">Barcode Produk (opsional)</label>
                        <input id="p-barcode" v-model="form.barcode" class="input" :class="{ 'is-invalid': errors.barcode }" type="text" maxlength="64" />
                        <span v-if="errors.barcode" class="form-error">{{ errors.barcode }}</span>
                    </div>
                </div>

                <div class="form-row form-row--2">
                    <div class="form-group">
                        <span class="form-label">Kategori</span>
                        <SearchSelect v-model="form.category_id" :options="categoryOptions" placeholder="Pilih kategori" search-placeholder="Cari kategori..." :invalid="Boolean(errors.category_id)" />
                        <span v-if="errors.category_id" class="form-error">{{ errors.category_id }}</span>
                    </div>
                    <div class="form-group">
                        <span class="form-label">Tipe Barang</span>
                        <SearchSelect v-model="form.product_type_id" :options="typeOptions" placeholder="Pilih tipe" search-placeholder="Cari tipe..." :invalid="Boolean(errors.product_type_id)" />
                        <span v-if="errors.product_type_id" class="form-error">{{ errors.product_type_id }}</span>
                    </div>
                    <div class="form-group">
                        <span class="form-label">Merek</span>
                        <SearchSelect v-model="form.brand_id" :options="brandOptions" placeholder="Pilih merek" search-placeholder="Cari merek..." :invalid="Boolean(errors.brand_id)" />
                        <span v-if="errors.brand_id" class="form-error">{{ errors.brand_id }}</span>
                    </div>
                    <div class="form-group">
                        <span class="form-label">Satuan</span>
                        <SearchSelect v-model="form.unit_id" :options="unitOptions" placeholder="Pilih satuan" search-placeholder="Cari satuan..." :invalid="Boolean(errors.unit_id)" />
                        <span v-if="errors.unit_id" class="form-error">{{ errors.unit_id }}</span>
                    </div>
                </div>

                <div class="form-row form-row--2">
                    <div class="form-group">
                        <label class="form-label" for="p-buy">Harga Beli (opsional)</label>
                        <input id="p-buy" v-model.number="form.purchase_price" class="input" :class="{ 'is-invalid': errors.purchase_price }" type="number" min="0" step="1" />
                        <span v-if="errors.purchase_price" class="form-error">{{ errors.purchase_price }}</span>
                    </div>
                    <div class="form-group">
                        <label class="form-label" for="p-sell">Harga Jual</label>
                        <input id="p-sell" v-model.number="form.selling_price" class="input" :class="{ 'is-invalid': errors.selling_price }" type="number" min="0" step="1" />
                        <span v-if="errors.selling_price" class="form-error">{{ errors.selling_price }}</span>
                    </div>
                    <div class="form-group">
                        <label class="form-label" for="p-stock">{{ isEdit ? 'Stok Saat Ini' : 'Stok Awal' }}</label>
                        <input
                            v-if="isEdit"
                            id="p-stock"
                            class="input"
                            type="text"
                            :value="formatNumber(form.stock)"
                            disabled
                        />
                        <input
                            v-else
                            id="p-stock"
                            v-model.number="form.stock"
                            class="input"
                            :class="{ 'is-invalid': errors.stock }"
                            type="number"
                            min="0"
                            step="1"
                        />
                        <span v-if="isEdit" class="hint">Ubah stok lewat tombol Sesuaikan Stok di halaman detail agar tercatat di riwayat.</span>
                        <span v-if="errors.stock" class="form-error">{{ errors.stock }}</span>
                    </div>
                    <div class="form-group">
                        <label class="form-label" for="p-min">Stok Minimum</label>
                        <input id="p-min" v-model.number="form.min_stock" class="input" :class="{ 'is-invalid': errors.min_stock }" type="number" min="0" step="1" />
                        <span v-if="errors.min_stock" class="form-error">{{ errors.min_stock }}</span>
                    </div>
                </div>

                <div class="form-group">
                    <label class="form-label" for="p-desc">Deskripsi Barang</label>
                    <textarea id="p-desc" v-model="form.description" class="input" :class="{ 'is-invalid': errors.description }" rows="4" maxlength="5000"></textarea>
                    <span v-if="errors.description" class="form-error">{{ errors.description }}</span>
                </div>
            </div>

            <div class="card">
                <h3>Foto &amp; Status</h3>

                <div class="uploader">
                    <div class="uploader__preview">
                        <img v-if="currentImage" :src="currentImage" alt="Pratinjau foto barang" />
                        <AppIcon v-else name="image" :size="40" />
                    </div>
                    <div class="uploader__actions">
                        <button class="btn btn-outline btn-sm" type="button" @click="fileInput.click()">
                            <AppIcon name="upload" :size="16" /> Pilih Foto
                        </button>
                        <button v-if="currentImage" class="btn btn-outline-danger btn-sm" type="button" @click="clearImage">
                            <AppIcon name="trash" :size="16" /> Hapus Foto
                        </button>
                    </div>
                    <input ref="fileInput" type="file" accept="image/jpeg,image/png,image/webp" hidden @change="pickFile" />
                    <span class="hint">JPG, PNG, atau WEBP. Maksimal 2 MB.</span>
                    <span v-if="errors.image" class="form-error">{{ errors.image }}</span>
                </div>

                <hr style="border: 0; border-top: 1px solid var(--border-color); margin: 20px 0" />

                <label class="check">
                    <input v-model="form.is_active" type="checkbox" />
                    Barang aktif
                </label>
                <p class="hint" style="margin-top: 6px">Barang nonaktif tidak tampil di toko customer.</p>

                <button class="btn btn-primary btn-block" type="submit" :disabled="saving" style="margin-top: 20px">
                    {{ saving ? 'Menyimpan...' : isEdit ? 'Simpan Perubahan' : 'Simpan Barang' }}
                </button>
            </div>
        </form>
    </section>
</template>
