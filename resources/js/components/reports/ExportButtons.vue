<script setup>
import { ref } from 'vue';
import http from '@/lib/http';
import AppIcon from '@/components/common/AppIcon.vue';
import { useToast } from '@/composables/useToast';
import { downloadBlob, filenameFrom, readErrorMessage } from '@/lib/download';
import { printHtml } from '@/lib/print';

const props = defineProps({
    endpoint: { type: String, required: true },
    params: { type: Object, default: () => ({}) },
    disabled: { type: Boolean, default: false },
});

const toast = useToast();
const busy = ref('');

function clean(params) {
    return Object.fromEntries(
        Object.entries(params).filter(([, value]) => value !== undefined && value !== null && value !== ''),
    );
}

async function run(kind) {
    if (busy.value) {
        return;
    }

    busy.value = kind;

    try {
        if (kind === 'print') {
            const { data } = await http.get(props.endpoint, {
                params: clean({ ...props.params, format: 'print' }),
                responseType: 'text',
                headers: { Accept: 'text/html' },
            });

            await printHtml(data);
        } else {
            const response = await http.get(props.endpoint, {
                params: clean({ ...props.params, format: kind }),
                responseType: 'blob',
            });

            downloadBlob(response.data, filenameFrom(response.headers['content-disposition'], `laporan.${kind}`));
            toast.success(kind === 'pdf' ? 'Laporan PDF diunduh.' : 'Laporan Excel diunduh.');
        }
    } catch (error) {
        toast.error(await readErrorMessage(error));
    } finally {
        busy.value = '';
    }
}
</script>

<template>
    <div class="action-row">
        <button class="btn btn-outline" type="button" :disabled="disabled || busy !== ''" @click="run('pdf')">
            <AppIcon name="file-text" :size="18" /> {{ busy === 'pdf' ? 'Menyiapkan...' : 'Export PDF' }}
        </button>
        <button class="btn btn-outline" type="button" :disabled="disabled || busy !== ''" @click="run('xlsx')">
            <AppIcon name="download" :size="18" /> {{ busy === 'xlsx' ? 'Menyiapkan...' : 'Export Excel' }}
        </button>
        <button class="btn btn-outline" type="button" :disabled="disabled || busy !== ''" @click="run('print')">
            <AppIcon name="printer" :size="18" /> {{ busy === 'print' ? 'Menyiapkan...' : 'Cetak' }}
        </button>
    </div>
</template>
