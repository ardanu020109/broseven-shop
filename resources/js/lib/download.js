export function filenameFrom(disposition, fallback) {
    const match = /filename\*?=(?:UTF-8'')?"?([^";]+)"?/i.exec(disposition ?? '');

    if (!match) {
        return fallback;
    }

    try {
        return decodeURIComponent(match[1]);
    } catch {
        return match[1];
    }
}

export function downloadBlob(blob, filename) {
    const url = URL.createObjectURL(blob);
    const link = document.createElement('a');

    link.href = url;
    link.download = filename;
    document.body.appendChild(link);
    link.click();
    link.remove();

    setTimeout(() => URL.revokeObjectURL(url), 2000);
}

export async function readErrorMessage(error, fallback = 'Gagal membuat laporan. Coba lagi.') {
    let body = error.response?.data;

    if (body instanceof Blob) {
        try {
            body = JSON.parse(await body.text());
        } catch {
            body = null;
        }
    } else if (typeof body === 'string') {
        try {
            body = JSON.parse(body);
        } catch {
            body = null;
        }
    }

    if (body?.errors) {
        const first = Object.values(body.errors)[0];

        return Array.isArray(first) ? first[0] : String(first);
    }

    if (error.response?.status === 429) {
        return 'Terlalu banyak permintaan export. Tunggu sebentar lalu coba lagi.';
    }

    return body?.message ?? fallback;
}
