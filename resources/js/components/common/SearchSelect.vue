<script setup>
import { computed, nextTick, onBeforeUnmount, onMounted, ref, watch } from 'vue';
import AppIcon from '@/components/common/AppIcon.vue';

const props = defineProps({
    modelValue: { type: Number, default: null },
    options: { type: Array, default: () => [] },
    placeholder: { type: String, default: 'Pilih...' },
    searchPlaceholder: { type: String, default: 'Cari...' },
    clearable: { type: Boolean, default: true },
    disabled: { type: Boolean, default: false },
    invalid: { type: Boolean, default: false },
});

const emit = defineEmits(['update:modelValue']);

const root = ref(null);
const searchInput = ref(null);
const listEl = ref(null);
const open = ref(false);
const query = ref('');
const highlighted = ref(0);

const selected = computed(() => props.options.find((option) => option.id === props.modelValue) ?? null);

const filtered = computed(() => {
    const term = query.value.trim().toLowerCase();

    if (term === '') {
        return props.options;
    }

    return props.options.filter((option) => option.name.toLowerCase().includes(term));
});

watch(query, () => {
    highlighted.value = 0;
});

function scrollToHighlighted() {
    listEl.value?.children[highlighted.value]?.scrollIntoView({ block: 'nearest' });
}

async function show() {
    open.value = true;
    query.value = '';

    const index = filtered.value.findIndex((option) => option.id === props.modelValue);
    highlighted.value = index >= 0 ? index : 0;

    await nextTick();
    searchInput.value?.focus();
    scrollToHighlighted();
}

function close() {
    open.value = false;
    query.value = '';
}

async function toggle() {
    if (props.disabled) {
        return;
    }

    if (open.value) {
        close();
    } else {
        await show();
    }
}

function pick(option) {
    emit('update:modelValue', option.id);
    close();
}

function clear() {
    emit('update:modelValue', null);
    close();
}

function move(step) {
    if (filtered.value.length === 0) {
        return;
    }

    highlighted.value = (highlighted.value + step + filtered.value.length) % filtered.value.length;
    nextTick(scrollToHighlighted);
}

function onKeydown(event) {
    if (event.key === 'ArrowDown') {
        event.preventDefault();
        move(1);
    } else if (event.key === 'ArrowUp') {
        event.preventDefault();
        move(-1);
    } else if (event.key === 'Enter') {
        event.preventDefault();
        const option = filtered.value[highlighted.value];

        if (option) {
            pick(option);
        }
    } else if (event.key === 'Escape') {
        event.preventDefault();
        event.stopPropagation();
        close();
    }
}

function onOutside(event) {
    if (open.value && root.value && !root.value.contains(event.target)) {
        close();
    }
}

onMounted(() => document.addEventListener('mousedown', onOutside));
onBeforeUnmount(() => document.removeEventListener('mousedown', onOutside));
</script>

<template>
    <div
        ref="root"
        class="ss"
        :class="{ 'ss--open': open, 'ss--invalid': invalid, 'ss--disabled': disabled }"
    >
        <button
            class="ss__control"
            type="button"
            aria-haspopup="listbox"
            :aria-expanded="open"
            :disabled="disabled"
            @click="toggle"
        >
            <span v-if="selected" class="ss__value">{{ selected.name }}</span>
            <span v-else class="ss__placeholder">{{ placeholder }}</span>
            <AppIcon name="chevron-down" :size="18" />
        </button>

        <div v-if="open" class="ss__panel">
            <input
                ref="searchInput"
                v-model="query"
                class="input"
                type="text"
                :placeholder="searchPlaceholder"
                @keydown="onKeydown"
            />

            <button v-if="clearable && modelValue !== null" class="ss__clear" type="button" @click="clear">
                Kosongkan pilihan
            </button>

            <ul ref="listEl" class="ss__list" role="listbox">
                <li
                    v-for="(option, index) in filtered"
                    :key="option.id"
                    class="ss__option"
                    :class="{
                        'ss__option--active': index === highlighted,
                        'ss__option--selected': option.id === modelValue,
                    }"
                    role="option"
                    :aria-selected="option.id === modelValue"
                    @mousedown.prevent="pick(option)"
                    @mouseenter="highlighted = index"
                >
                    {{ option.name }}
                </li>
            </ul>

            <p v-if="filtered.length === 0" class="ss__empty">Tidak ada hasil.</p>
        </div>
    </div>
</template>
