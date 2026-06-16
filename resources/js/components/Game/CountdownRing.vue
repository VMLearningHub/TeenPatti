<script setup lang="ts">
import { useRafFn } from '@vueuse/core';
import { computed, ref, watch } from 'vue';
import { serverNow } from '@/composables/useServerClock';

const props = defineProps<{
    deadline: string | null;
    startedAt: string | null;
    active: boolean;
}>();

const RADIUS = 46;
const CIRC = 2 * Math.PI * RADIUS;

// Fraction of time remaining (1 → full, 0 → expired).
const fraction = ref(1);

const deadlineMs = computed(() =>
    props.deadline ? Date.parse(props.deadline) : null,
);
const totalMs = computed(() => {
    if (!props.deadline || !props.startedAt) {
        return null;
    }

    const total = Date.parse(props.deadline) - Date.parse(props.startedAt);

    return total > 0 ? total : null;
});

const { pause, resume } = useRafFn(
    () => {
        if (deadlineMs.value === null || totalMs.value === null) {
            fraction.value = 1;

            return;
        }

        const remaining = deadlineMs.value - serverNow();
        fraction.value = Math.max(0, Math.min(1, remaining / totalMs.value));
    },
    { immediate: false },
);

watch(
    () => props.active && props.deadline !== null,
    (on) => {
        if (on) {
            resume();
        } else {
            pause();
            fraction.value = 1;
        }
    },
    { immediate: true },
);

const dashOffset = computed(() => CIRC * (1 - fraction.value));
const strokeColor = computed(() => {
    if (fraction.value > 0.5) {
        return '#34d399';
    } // emerald

    if (fraction.value > 0.25) {
        return '#fbbf24';
    } // amber

    return '#f87171'; // red
});
</script>

<template>
    <svg
        v-if="active && deadline"
        class="pointer-events-none absolute -inset-1 h-[calc(100%+0.5rem)] w-[calc(100%+0.5rem)] -rotate-90"
        viewBox="0 0 100 100"
        fill="none"
    >
        <circle
            cx="50"
            cy="50"
            :r="RADIUS"
            stroke="currentColor"
            class="text-zinc-700/40"
            stroke-width="3"
        />
        <circle
            cx="50"
            cy="50"
            :r="RADIUS"
            :stroke="strokeColor"
            stroke-width="3"
            stroke-linecap="round"
            :stroke-dasharray="CIRC"
            :stroke-dashoffset="dashOffset"
            style="transition: stroke 0.4s linear"
        />
    </svg>
</template>
