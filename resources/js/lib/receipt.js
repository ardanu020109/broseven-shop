import QRCode from 'qrcode';
import { formatDateTime, formatRupiah } from '@/lib/utils';

const FONT = 'system-ui, -apple-system, "Segoe UI", Roboto, Arial, sans-serif';

function fit(ctx, text, maxWidth) {
    if (ctx.measureText(text).width <= maxWidth) {
        return text;
    }

    let result = text;

    while (result.length > 1 && ctx.measureText(`${result}…`).width > maxWidth) {
        result = result.slice(0, -1);
    }

    return `${result}…`;
}

function cssVar(name, fallback) {
    return getComputedStyle(document.documentElement).getPropertyValue(name).trim() || fallback;
}

export async function downloadReceipt(order, storeName) {
    const dark = cssVar('--dark', '#26547C');
    const gray = '#64748B';
    const width = 640;
    const pad = 36;
    const headerH = 96;
    const infoRows = 5;
    const infoH = infoRows * 28 + 40;
    const itemsHeadH = 40;
    const rowH = 32;
    const totalH = 70;
    const qrSize = 240;
    const showQr = Boolean(order.barcode_code);
    const qrBlockH = showQr ? qrSize + 90 : 0;
    const footerH = 90;
    const height = headerH + infoH + itemsHeadH + order.items.length * rowH + totalH + qrBlockH + footerH;
    const scale = 2;

    const canvas = document.createElement('canvas');
    canvas.width = width * scale;
    canvas.height = height * scale;

    const ctx = canvas.getContext('2d');
    ctx.scale(scale, scale);
    ctx.fillStyle = '#FFFFFF';
    ctx.fillRect(0, 0, width, height);

    ctx.fillStyle = dark;
    ctx.fillRect(0, 0, width, headerH);
    ctx.fillStyle = '#FFFFFF';
    ctx.textAlign = 'left';
    ctx.font = `700 26px ${FONT}`;
    ctx.fillText(fit(ctx, storeName, width - pad * 2), pad, 46);
    ctx.font = `500 15px ${FONT}`;
    ctx.fillText('Bukti Pesanan', pad, 72);

    const info = [
        ['No. Pesanan', order.order_number],
        ['Tanggal', formatDateTime(order.created_at)],
        ['Nama', order.customer_name],
        ['Telepon', order.customer_phone],
        ['Status', order.status_label],
    ];

    let y = headerH + 38;

    for (const [label, value] of info) {
        ctx.textAlign = 'left';
        ctx.fillStyle = gray;
        ctx.font = `500 14px ${FONT}`;
        ctx.fillText(label, pad, y);
        ctx.fillStyle = dark;
        ctx.font = `700 15px ${FONT}`;
        ctx.fillText(fit(ctx, String(value), width - pad * 2 - 140), pad + 140, y);
        y += 28;
    }

    y = headerH + infoH;
    ctx.strokeStyle = '#E5E7EB';
    ctx.lineWidth = 1;
    ctx.beginPath();
    ctx.moveTo(pad, y);
    ctx.lineTo(width - pad, y);
    ctx.stroke();

    ctx.fillStyle = gray;
    ctx.font = `700 12px ${FONT}`;
    ctx.textAlign = 'left';
    ctx.fillText('BARANG', pad, y + 26);
    ctx.fillText('JUMLAH', 340, y + 26);
    ctx.textAlign = 'right';
    ctx.fillText('SUBTOTAL', width - pad, y + 26);

    y += itemsHeadH;

    for (const item of order.items) {
        ctx.fillStyle = dark;
        ctx.font = `600 14px ${FONT}`;
        ctx.textAlign = 'left';
        ctx.fillText(fit(ctx, item.product_name, 270), pad, y + 14);
        ctx.fillStyle = gray;
        ctx.font = `500 13px ${FONT}`;
        ctx.fillText(`${item.quantity} × ${formatRupiah(item.unit_price)}`, 340, y + 14);
        ctx.fillStyle = dark;
        ctx.font = `700 14px ${FONT}`;
        ctx.textAlign = 'right';
        ctx.fillText(formatRupiah(item.subtotal), width - pad, y + 14);
        y += rowH;
    }

    ctx.strokeStyle = '#E5E7EB';
    ctx.beginPath();
    ctx.moveTo(pad, y + 6);
    ctx.lineTo(width - pad, y + 6);
    ctx.stroke();

    ctx.fillStyle = dark;
    ctx.textAlign = 'left';
    ctx.font = `700 17px ${FONT}`;
    ctx.fillText('Total Pembayaran', pad, y + 40);
    ctx.textAlign = 'right';
    ctx.font = `800 22px ${FONT}`;
    ctx.fillText(formatRupiah(order.total_amount), width - pad, y + 42);
    y += totalH;

    if (showQr) {
        const dataUrl = await QRCode.toDataURL(order.barcode_code, {
            width: qrSize * 2,
            margin: 1,
            errorCorrectionLevel: 'M',
        });
        const image = new Image();
        image.src = dataUrl;
        await image.decode();

        ctx.drawImage(image, (width - qrSize) / 2, y, qrSize, qrSize);
        ctx.fillStyle = gray;
        ctx.textAlign = 'center';
        ctx.font = `500 13px ${FONT}`;
        ctx.fillText('Tunjukkan QR ini kepada kasir', width / 2, y + qrSize + 28);
        ctx.font = `500 12px ui-monospace, Menlo, Consolas, monospace`;
        ctx.fillText(order.barcode_code, width / 2, y + qrSize + 50);
        y += qrBlockH;
    }

    ctx.fillStyle = gray;
    ctx.textAlign = 'center';
    ctx.font = `500 13px ${FONT}`;
    ctx.fillText('Pembayaran dilakukan langsung di kasir.', width / 2, y + 24);
    ctx.fillText('Simpan bukti ini sampai pesanan selesai.', width / 2, y + 46);

    const blob = await new Promise((resolve) => canvas.toBlob(resolve, 'image/png'));

    if (!blob) {
        throw new Error('Gagal membuat gambar bukti pesanan.');
    }

    const url = URL.createObjectURL(blob);
    const link = document.createElement('a');
    link.href = url;
    link.download = `Bukti-${order.order_number}.png`;
    document.body.appendChild(link);
    link.click();
    link.remove();
    setTimeout(() => URL.revokeObjectURL(url), 2000);
}
