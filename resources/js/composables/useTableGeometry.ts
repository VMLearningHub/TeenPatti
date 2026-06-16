import { ref } from 'vue';

/**
 * Registers the live DOM positions of seats, the pot, and the overlay layer so
 * the animation layer can measure source/target rects on demand. Positions are
 * never cached — the table is a responsive ellipse, so everything is measured
 * at animation time via getBoundingClientRect.
 */
export function useTableGeometry() {
    const seatEls = new Map<number, HTMLElement>();
    const potEl = ref<HTMLElement | null>(null);
    const overlayEl = ref<HTMLElement | null>(null);

    function registerSeat(seat: number, el: Element | null): void {
        if (el instanceof HTMLElement) {
            seatEls.set(seat, el);
        } else {
            seatEls.delete(seat);
        }
    }

    function seatRect(seat: number): DOMRect | null {
        return seatEls.get(seat)?.getBoundingClientRect() ?? null;
    }

    function potRect(): DOMRect | null {
        return potEl.value?.getBoundingClientRect() ?? null;
    }

    function overlayRect(): DOMRect | null {
        return overlayEl.value?.getBoundingClientRect() ?? null;
    }

    return { registerSeat, seatRect, potRect, overlayRect, potEl, overlayEl };
}

export type TableGeometry = ReturnType<typeof useTableGeometry>;
