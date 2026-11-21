const rupiahFormatter = new Intl.NumberFormat('id-ID', {
    style: 'currency',
    currency: 'IDR',
    maximumFractionDigits: 0,
});

const numberFormatter = new Intl.NumberFormat('id-ID');

const dateTimeFormatter = new Intl.DateTimeFormat('id-ID', {
    dateStyle: 'medium',
    timeStyle: 'short',
});

export function formatRupiah(value) {
    if (value === null || value === undefined || value === '') {
        return '-';
    }

    return rupiahFormatter.format(Number(value));
}

export function formatNumber(value) {
    if (value === null || value === undefined || value === '') {
        return '-';
    }

    return numberFormatter.format(Number(value));
}

export function formatDateTime(value) {
    if (!value) {
        return '-';
    }

    return dateTimeFormatter.format(new Date(value));
}

export function debounce(fn, wait = 350) {
    let timer = null;

    return (...args) => {
        clearTimeout(timer);
        timer = setTimeout(() => fn(...args), wait);
    };
}

export function errorMessage(error, fallback = 'Terjadi kesalahan. Coba lagi.') {
    if (error?.response?.status === 422) {
        return 'Periksa kembali isian Anda.';
    }

    return error?.response?.data?.message ?? fallback;
}

export function validationErrors(error) {
    if (error?.response?.status !== 422) {
        return {};
    }

    const errors = error.response.data?.errors ?? {};

    return Object.fromEntries(
        Object.entries(errors).map(([field, messages]) => [field, Array.isArray(messages) ? messages[0] : messages]),
    );
}
