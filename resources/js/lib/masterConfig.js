export const MASTER_CONFIG = {
    categories: {
        title: 'Kategori Barang',
        singular: 'Kategori',
        endpoint: '/categories',
        nameMax: 100,
        extra: { key: 'description', label: 'Deskripsi', type: 'textarea', maxlength: 1000 },
    },
    'product-types': {
        title: 'Tipe Barang',
        singular: 'Tipe Barang',
        endpoint: '/product-types',
        nameMax: 100,
        extra: { key: 'description', label: 'Deskripsi', type: 'textarea', maxlength: 1000 },
    },
    brands: {
        title: 'Merek',
        singular: 'Merek',
        endpoint: '/brands',
        nameMax: 100,
        extra: { key: 'description', label: 'Deskripsi', type: 'textarea', maxlength: 1000 },
    },
    units: {
        title: 'Satuan Barang',
        singular: 'Satuan',
        endpoint: '/units',
        nameMax: 50,
        extra: { key: 'abbreviation', label: 'Singkatan', type: 'text', maxlength: 20 },
    },
};
