function pick(set) {
    const buffer = new Uint32Array(1);
    crypto.getRandomValues(buffer);

    return set[buffer[0] % set.length];
}

export function generatePassword() {
    const letters = 'ABCDEFGHJKMNPQRSTUVWXYZabcdefghjkmnpqrstuvwxyz';
    const digits = '23456789';
    const chars = [];

    for (let i = 0; i < 10; i += 1) {
        chars.push(pick(letters));
    }

    for (let i = 0; i < 2; i += 1) {
        chars.push(pick(digits));
    }

    for (let i = chars.length - 1; i > 0; i -= 1) {
        const buffer = new Uint32Array(1);
        crypto.getRandomValues(buffer);
        const j = buffer[0] % (i + 1);
        [chars[i], chars[j]] = [chars[j], chars[i]];
    }

    return chars.join('');
}
