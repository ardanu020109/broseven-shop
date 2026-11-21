<script setup>
import { computed } from 'vue';

const props = defineProps({
    points: { type: Array, default: () => [] },
    type: { type: String, default: 'bar' },
    color: { type: String, default: 'var(--primary)' },
    formatter: { type: Function, default: (value) => String(value) },
});

const W = 640;
const H = 250;
const PL = 52;
const PR = 12;
const PT = 12;
const PB = 30;
const plotW = W - PL - PR;
const plotH = H - PT - PB;
const compact = new Intl.NumberFormat('id-ID', { notation: 'compact', maximumFractionDigits: 1 });

function niceCeil(value) {
    if (value <= 0) {
        return 4;
    }

    const exponent = 10 ** Math.floor(Math.log10(value));
    const fraction = value / exponent;
    const nice = fraction <= 1 ? 1 : fraction <= 2 ? 2 : fraction <= 5 ? 5 : 10;

    return nice * exponent;
}

const hasData = computed(() => props.points.some((point) => point.value > 0));
const niceMax = computed(() => niceCeil(Math.max(...props.points.map((point) => point.value), 0)));
const ticks = computed(() => [0, 1, 2, 3, 4].map((index) => (niceMax.value / 4) * index));
const step = computed(() => plotW / Math.max(props.points.length, 1));
const labelEvery = computed(() => Math.ceil(props.points.length / 10));
const barWidth = computed(() => Math.max(2, step.value * 0.62));

const x = (index) => PL + step.value * index + step.value / 2;
const y = (value) => PT + plotH - (value / niceMax.value) * plotH;

const linePath = computed(() =>
    props.points.map((point, index) => `${index === 0 ? 'M' : 'L'}${x(index).toFixed(1)},${y(point.value).toFixed(1)}`).join(' '),
);
</script>

<template>
    <div class="chart" :style="{ '--chart-color': color }">
        <svg :viewBox="`0 0 ${W} ${H}`" role="img" aria-label="Grafik">
            <g v-for="tick in ticks" :key="tick">
                <line class="chart__grid" :x1="PL" :x2="W - PR" :y1="y(tick)" :y2="y(tick)" />
                <text class="chart__tick" :x="PL - 8" :y="y(tick) + 4" text-anchor="end">{{ compact.format(tick) }}</text>
            </g>

            <template v-if="type === 'bar'">
                <rect
                    v-for="(point, index) in points"
                    :key="point.title + index"
                    class="chart__bar"
                    :x="x(index) - barWidth / 2"
                    :y="y(point.value)"
                    :width="barWidth"
                    :height="Math.max(0, PT + plotH - y(point.value))"
                    rx="3"
                >
                    <title>{{ point.title }}: {{ formatter(point.value) }}</title>
                </rect>
            </template>

            <template v-else>
                <path class="chart__line" :d="linePath" />
                <circle
                    v-for="(point, index) in points"
                    v-show="points.length <= 40"
                    :key="point.title + index"
                    class="chart__dot"
                    :cx="x(index)"
                    :cy="y(point.value)"
                    r="3.5"
                >
                    <title>{{ point.title }}: {{ formatter(point.value) }}</title>
                </circle>
            </template>

            <template v-for="(point, index) in points" :key="'l' + index">
                <text
                    v-if="index % labelEvery === 0"
                    class="chart__tick"
                    :x="x(index)"
                    :y="H - 9"
                    text-anchor="middle"
                >
                    {{ point.label }}
                </text>
            </template>

            <text v-if="!hasData" class="chart__empty" :x="W / 2" :y="H / 2">Belum ada data pada periode ini</text>
        </svg>
    </div>
</template>
