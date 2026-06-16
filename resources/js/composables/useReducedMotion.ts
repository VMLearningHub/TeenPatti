import { usePreferredReducedMotion } from '@vueuse/core';
import { computed } from 'vue';

/**
 * Central gate for all animations. When the user prefers reduced motion the
 * conductor takes instant, no-motion paths everywhere.
 */
export function useReducedMotion() {
    const preference = usePreferredReducedMotion();
    const shouldAnimate = computed(() => preference.value !== 'reduce');

    return { shouldAnimate };
}
