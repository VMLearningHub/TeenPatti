import type { TableGeometry } from '@/composables/useTableGeometry';

interface FlyOptions {
    from: DOMRect | null;
    to: DOMRect | null;
    /** CSS classes for the transient sprite. */
    className: string;
    /** Inner HTML (e.g. a card rank, or empty for a chip). */
    content?: string;
    size?: number;
    duration?: number;
    scaleTo?: number;
    rotate?: number;
    easing?: string;
}

/**
 * Flies transient sprites (chips, cards) between table positions using the Web
 * Animations API on transform/opacity only — GPU-composited, off the main
 * thread. Sprites live in the overlay layer and self-remove on finish/cancel.
 */
export function useFlyingChips(geometry: TableGeometry) {
    const live = new Set<Animation>();

    async function fly(opts: FlyOptions): Promise<void> {
        const overlay = geometry.overlayEl.value;
        const overlayRect = geometry.overlayRect();

        if (!overlay || !overlayRect || !opts.from || !opts.to) {
            return;
        }

        const size = opts.size ?? 28;
        const startX = opts.from.left + opts.from.width / 2 - overlayRect.left;
        const startY = opts.from.top + opts.from.height / 2 - overlayRect.top;
        const endX = opts.to.left + opts.to.width / 2 - overlayRect.left;
        const endY = opts.to.top + opts.to.height / 2 - overlayRect.top;

        const el = document.createElement('div');
        el.className = opts.className;

        if (opts.content) {
            el.innerHTML = opts.content;
        }

        el.style.position = 'absolute';
        el.style.left = `${startX - size / 2}px`;
        el.style.top = `${startY - size / 2}px`;
        el.style.willChange = 'transform, opacity';
        el.style.pointerEvents = 'none';
        overlay.appendChild(el);

        const dx = endX - startX;
        const dy = endY - startY;
        const anim = el.animate(
            [
                {
                    transform: 'translate(0,0) scale(1) rotate(0deg)',
                    opacity: 1,
                },
                {
                    transform: `translate(${dx}px, ${dy}px) scale(${opts.scaleTo ?? 0.6}) rotate(${opts.rotate ?? 0}deg)`,
                    opacity: 0.9,
                },
            ],
            {
                duration: opts.duration ?? 380,
                easing: opts.easing ?? 'cubic-bezier(.4,0,.2,1)',
                fill: 'forwards',
            },
        );
        live.add(anim);

        try {
            await anim.finished;
        } catch {
            // cancelled — fall through to cleanup
        } finally {
            live.delete(anim);
            el.remove();
        }
    }

    function cancelAll(): void {
        for (const anim of live) {
            anim.cancel();
        }

        live.clear();
    }

    return { fly, cancelAll };
}
