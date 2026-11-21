<script setup>
import { computed } from 'vue';

const props = defineProps({
    page: { type: Number, default: 1 },
    lastPage: { type: Number, default: 1 },
    total: { type: Number, default: 0 },
    from: { type: Number, default: 0 },
    to: { type: Number, default: 0 },
});

const emit = defineEmits(['change']);

const pages = computed(() => {
    const start = Math.max(1, props.page - 2);
    const end = Math.min(props.lastPage, props.page + 2);
    const list = [];

    for (let number = start; number <= end; number += 1) {
        list.push(number);
    }

    return list;
});

function go(target) {
    if (target < 1 || target > props.lastPage || target === props.page) {
        return;
    }

    emit('change', target);
}
</script>

<template>
    <div v-if="total > 0" class="pagination">
        <span class="pagination__info">Menampilkan {{ from }}-{{ to }} dari {{ total }} data</span>

        <div v-if="lastPage > 1" class="pagination__nav">
            <button class="pagination__btn" type="button" :disabled="page <= 1" @click="go(page - 1)">Sebelumnya</button>
            <button
                v-for="number in pages"
                :key="number"
                class="pagination__btn"
                :class="{ 'pagination__btn--active': number === page }"
                type="button"
                @click="go(number)"
            >
                {{ number }}
            </button>
            <button class="pagination__btn" type="button" :disabled="page >= lastPage" @click="go(page + 1)">Berikutnya</button>
        </div>
    </div>
</template>
