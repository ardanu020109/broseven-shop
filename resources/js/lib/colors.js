const HEX = /^#[0-9a-fA-F]{6}$/;

export function isHex(value) {
    return typeof value === 'string' && HEX.test(value);
}

export function normalizeHex(value) {
    const text = String(value ?? '').trim();
    const withHash = text.startsWith('#') ? text : `#${text}`;

    return withHash.toUpperCase();
}

function luminance(hex) {
    const [r, g, b] = [1, 3, 5].map((index) => parseInt(hex.slice(index, index + 2), 16) / 255);
    const channel = (c) => (c <= 0.03928 ? c / 12.92 : ((c + 0.055) / 1.055) ** 2.4);

    return 0.2126 * channel(r) + 0.7152 * channel(g) + 0.0722 * channel(b);
}

export function contrast(a, b) {
    const first = luminance(a);
    const second = luminance(b);

    return (Math.max(first, second) + 0.05) / (Math.min(first, second) + 0.05);
}
