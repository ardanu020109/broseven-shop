const KEY = 'broseven_orders_v1';
const MAX_SAVED = 20;

function read() {
    try {
        const parsed = JSON.parse(localStorage.getItem(KEY) ?? '[]');

        if (!Array.isArray(parsed)) {
            return [];
        }

        return parsed.filter(
            (order) => order && typeof order.order_number === 'string' && typeof order.token === 'string',
        );
    } catch {
        return [];
    }
}

function write(list) {
    try {
        localStorage.setItem(KEY, JSON.stringify(list.slice(0, MAX_SAVED)));
    } catch {
        // penyimpanan tidak tersedia, abaikan
    }
}

export function getSavedOrders() {
    return read();
}

export function saveOrder({ order_number, token, total_amount, created_at }) {
    const rest = read().filter((order) => order.order_number !== order_number);

    write([{ order_number, token, total_amount, created_at }, ...rest]);
}

export function findSavedToken(orderNumber) {
    return read().find((order) => order.order_number === orderNumber)?.token ?? null;
}

export function removeSavedOrder(orderNumber) {
    write(read().filter((order) => order.order_number !== orderNumber));
}
