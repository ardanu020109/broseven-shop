import { reactive } from 'vue';

const toasts = reactive([]);
let nextId = 0;

function remove(id) {
    const index = toasts.findIndex((toast) => toast.id === id);

    if (index >= 0) {
        toasts.splice(index, 1);
    }
}

function push(type, message, timeout) {
    const id = ++nextId;

    toasts.push({ id, type, message });
    setTimeout(() => remove(id), timeout);
}

export function useToast() {
    return {
        toasts,
        remove,
        success: (message) => push('success', message, 3500),
        error: (message) => push('error', message, 5500),
    };
}
