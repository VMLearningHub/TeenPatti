<script setup lang="ts">
import { ref, watch } from 'vue';
import type { Card } from '@/types/game';

const props = withDefaults(
    defineProps<{
        card: Card | null;
        size?: 'sm' | 'lg';
        flipDelay?: number;
    }>(),
    { size: 'sm', flipDelay: 0 },
);

const suitGlyph: Record<string, string> = { S: '♠', H: '♥', D: '♦', C: '♣' };
const suitClass: Record<string, string> = {
    S: 'text-zinc-100',
    H: 'text-rose-400',
    D: 'text-rose-400',
    C: 'text-zinc-100',
};

// `flipped` true → showing the face-down back. Reveal flips it to the face with
// a 3D rotateY transition once the card becomes known.
const flipped = ref(props.card === null);

watch(
    () => props.card,
    (card) => {
        if (card) {
            window.setTimeout(() => (flipped.value = false), props.flipDelay);
        } else {
            flipped.value = true;
        }
    },
);
</script>

<template>
    <div
        class="tp-card-flip relative"
        :class="size === 'lg' ? 'h-20 w-14' : 'h-10 w-7'"
        style="perspective: 600px"
    >
        <div
            class="relative h-full w-full transition-transform duration-500"
            style="transform-style: preserve-3d"
            :style="{
                transform: flipped ? 'rotateY(180deg)' : 'rotateY(0deg)',
            }"
        >
            <!-- Face -->
            <div
                class="absolute inset-0 flex items-center justify-center rounded-md border bg-zinc-900 font-bold"
                :class="[
                    size === 'lg' ? 'border-2 text-2xl' : 'text-sm',
                    card ? suitClass[card.suit] : '',
                ]"
                style="backface-visibility: hidden"
            >
                <template v-if="card">
                    {{ card.rank
                    }}<span :class="size === 'lg' ? '' : 'text-xs'">{{
                        suitGlyph[card.suit]
                    }}</span>
                </template>
            </div>
            <!-- Back (face down) -->
            <div
                class="absolute inset-0 flex items-center justify-center rounded-md border border-rose-300/30 bg-rose-800 text-rose-200"
                style="backface-visibility: hidden; transform: rotateY(180deg)"
            >
                <span class="opacity-70">•</span>
            </div>
        </div>
    </div>
</template>
