<script setup>
import { onBeforeUnmount, watch } from 'vue';
import AppIcon from '@/components/common/AppIcon.vue';

const props = defineProps({
    modelValue: { type: Boolean, default: false },
    title: { type: String, default: '' },
    width: { type: String, default: '520px' },
    closable: { type: Boolean, default: true },
});

const emit = defineEmits(['update:modelValue', 'close']);

function close() {
    if (!props.closable) {
        return;
    }

    emit('update:modelValue', false);
    emit('close');
}

function onKeydown(event) {
    if (event.key === 'Escape') {
        close();
    }
}

watch(
    () => props.modelValue,
    (open) => {
        document.body.style.overflow = open ? 'hidden' : '';

        if (open) {
            window.addEventListener('keydown', onKeydown);
        } else {
            window.removeEventListener('keydown', onKeydown);
        }
    },
    { immediate: true },
);

onBeforeUnmount(() => {
    document.body.style.overflow = '';
    window.removeEventListener('keydown', onKeydown);
});
</script>

<template>
    <Teleport to="body">
        <Transition name="modal">
            <div v-if="modelValue" class="modal" @mousedown.self="close">
                <div class="modal__dialog" :style="{ maxWidth: width }" role="dialog" aria-modal="true">
                    <header class="modal__header">
                        <h3>{{ title }}</h3>
                        <button class="modal__close" type="button" aria-label="Tutup" :disabled="!closable" @click="close">
                            <AppIcon name="close" />
                        </button>
                    </header>

                    <div class="modal__body">
                        <slot />
                    </div>

                    <footer v-if="$slots.footer" class="modal__footer">
                        <slot name="footer" />
                    </footer>
                </div>
            </div>
        </Transition>
    </Teleport>
</template>
