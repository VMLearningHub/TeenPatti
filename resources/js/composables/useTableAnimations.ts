import { ref } from 'vue';
import type { Ref } from 'vue';
import { fireConfetti } from '@/composables/useConfetti';
import { useFlyingChips } from '@/composables/useFlyingChips';
import { syncServerClock } from '@/composables/useServerClock';
import type { useSound } from '@/composables/useSound';
import type { TableGeometry } from '@/composables/useTableGeometry';
import type {
    GameEvent,
    HandCompletedPayload,
    HandStartedPayload,
    PlayerActedPayload,
} from '@/types/game';

export interface FloatingLabelItem {
    id: number;
    seat: number;
    text: string;
    tone: 'bet' | 'fold' | 'see' | 'show' | 'win';
}

const CHIP_CLASS = 'tp-chip';
const CARD_CLASS = 'tp-fly-card';

const ACTION_LABELS: Record<
    string,
    { text: string; tone: FloatingLabelItem['tone'] }
> = {
    chaal: { text: 'CHAAL', tone: 'bet' },
    blind_bet: { text: 'BLIND', tone: 'bet' },
    pack: { text: 'PACK', tone: 'fold' },
    see_cards: { text: 'SEE', tone: 'see' },
    show: { text: 'SHOW', tone: 'show' },
    sideshow_request: { text: 'SIDESHOW', tone: 'show' },
    sideshow_accept: { text: 'ACCEPT', tone: 'show' },
    sideshow_decline: { text: 'DECLINE', tone: 'fold' },
};

interface ConductorOptions {
    geometry: TableGeometry;
    reload: () => void;
    sound: ReturnType<typeof useSound>;
    shouldAnimate: Ref<boolean>;
    /** Resolve a user's current seat (for winner chip flights). */
    seatForUser: (userId: number) => number | null;
}

function delayThen(ms: number, fn: () => Promise<void> | void): Promise<void> {
    return new Promise((resolve) => {
        window.setTimeout(() => {
            Promise.resolve(fn()).finally(() => resolve());
        }, ms);
    });
}

/**
 * The animation conductor. Inertia props stay the source of truth; this layer
 * only plays transient animations off broadcast events and keeps an optimistic
 * pot number that reconciles to props. Events are deduped by hand id + the
 * monotonic action_seq so a replayed/already-applied event never re-animates.
 */
export function useTableAnimations(opts: ConductorOptions) {
    const fly = useFlyingChips(opts.geometry);

    const displayPot = ref(0);
    const floatingLabels = ref<FloatingLabelItem[]>([]);
    const dealing = ref(false);
    // Incremented whenever the pot grows, so the UI can re-trigger the bounce.
    const potBounce = ref(0);

    let labelId = 0;
    let lastSeq = 0;
    let currentHandId = 0;
    let potRaf: number | null = null;

    // --- pot number tween -----------------------------------------------------

    function tweenPot(to: number): void {
        if (!opts.shouldAnimate.value) {
            displayPot.value = to;

            return;
        }

        const from = displayPot.value;

        if (to > from + 0.005) {
            potBounce.value++;
        }

        if (Math.abs(from - to) < 0.005) {
            displayPot.value = to;

            return;
        }

        if (potRaf !== null) {
            cancelAnimationFrame(potRaf);
        }

        const start = performance.now();
        const dur = 500;
        const step = (now: number) => {
            const t = Math.min(1, (now - start) / dur);
            const eased = 1 - Math.pow(1 - t, 3);
            displayPot.value = from + (to - from) * eased;

            if (t < 1) {
                potRaf = requestAnimationFrame(step);
            } else {
                displayPot.value = to;
                potRaf = null;
            }
        };
        potRaf = requestAnimationFrame(step);
    }

    /** Reconcile the pot to authoritative props (no animation when idle). */
    function syncPot(value: number): void {
        if (potRaf === null) {
            displayPot.value = value;
        }
    }

    // --- floating labels ------------------------------------------------------

    function pushLabel(
        seat: number,
        text: string,
        tone: FloatingLabelItem['tone'],
    ): void {
        const id = ++labelId;
        floatingLabels.value = [
            ...floatingLabels.value.filter((l) => l.seat !== seat),
            { id, seat, text, tone },
        ].slice(-3);
        window.setTimeout(() => {
            floatingLabels.value = floatingLabels.value.filter(
                (l) => l.id !== id,
            );
        }, 2000);
    }

    // --- choreography ---------------------------------------------------------

    async function dealCards(
        order: Array<{ seat_position: number }>,
    ): Promise<void> {
        const total = order.length * 3;

        if (total === 0) {
            return;
        }

        const stagger = Math.max(
            70,
            Math.min(150, (2600 - 320) / Math.max(1, total - 1)),
        );

        dealing.value = true;
        const flights: Promise<void>[] = [];
        let i = 0;

        for (let round = 0; round < 3; round++) {
            for (const o of order) {
                const at = i * stagger;
                i++;
                flights.push(
                    delayThen(at, () =>
                        fly.fly({
                            from: opts.geometry.potRect(),
                            to: opts.geometry.seatRect(o.seat_position),
                            className: CARD_CLASS,
                            size: 26,
                            duration: 320,
                            scaleTo: 1,
                            rotate: -6,
                        }),
                    ),
                );
            }
        }

        await Promise.all(flights);
        dealing.value = false;
    }

    async function onHandStarted(p: HandStartedPayload): Promise<void> {
        floatingLabels.value = [];
        currentHandId = p.hand_id;
        lastSeq = p.action_seq;

        const target = Number(p.pot_amount);

        if (!opts.shouldAnimate.value) {
            displayPot.value = target;

            return;
        }

        const order =
            p.deal_order ??
            p.players.map((pl) => ({ seat_position: pl.seat_position }));
        await dealCards(order);

        // Boot chips fly in from each seated player.
        for (const pl of p.players) {
            void fly.fly({
                from: opts.geometry.seatRect(pl.seat_position),
                to: opts.geometry.potRect(),
                className: CHIP_CLASS,
                duration: 360,
                scaleTo: 0.5,
            });
        }

        tweenPot(target);
    }

    function onPlayerActed(p: PlayerActedPayload): void {
        const label = ACTION_LABELS[p.action];

        if (label) {
            pushLabel(p.seat_position, label.text, label.tone);
        }

        const delta = Number(p.pot_delta);

        if (delta > 0 && opts.shouldAnimate.value) {
            const count = delta >= 40 ? 3 : delta >= 20 ? 2 : 1;

            for (let k = 0; k < count; k++) {
                void delayThen(k * 60, () =>
                    fly.fly({
                        from: opts.geometry.seatRect(p.seat_position),
                        to: opts.geometry.potRect(),
                        className: CHIP_CLASS,
                        duration: 380,
                        scaleTo: 0.55,
                    }),
                );
            }
        }

        if (delta > 0) {
            tweenPot(Number(p.pot_amount));
        }
    }

    function onHandCompleted(p: HandCompletedPayload): void {
        for (const payout of p.payouts) {
            const seat = opts.seatForUser(payout.user_id);

            if (seat == null) {
                continue;
            }

            pushLabel(seat, `🎉 +₹${Math.round(Number(payout.amount))}`, 'win');

            if (opts.shouldAnimate.value) {
                for (let k = 0; k < 5; k++) {
                    void delayThen(k * 60, () =>
                        fly.fly({
                            from: opts.geometry.potRect(),
                            to: opts.geometry.seatRect(seat),
                            className: CHIP_CLASS,
                            duration: 480,
                            scaleTo: 0.5,
                        }),
                    );
                }
            }
        }

        if (opts.shouldAnimate.value) {
            void fireConfetti();
        }

        opts.sound.playWin();
        tweenPot(0);
    }

    // --- event entry point ----------------------------------------------------

    function handleEvent(event: GameEvent): void {
        syncServerClock(event.payload.server_now);

        if (event.type === 'HandStarted') {
            void onHandStarted(event.payload);
            opts.reload();

            return;
        }

        // Ignore events for a hand we're not tracking (resync via props instead).
        if (event.payload.hand_id !== currentHandId) {
            currentHandId = event.payload.hand_id;
            lastSeq = event.payload.action_seq;
            opts.reload();

            return;
        }

        // Dedup / ordering: drop already-applied or replayed events.
        if (event.payload.action_seq <= lastSeq) {
            return;
        }

        lastSeq = event.payload.action_seq;

        if (event.type === 'PlayerActed') {
            onPlayerActed(event.payload);
        } else if (event.type === 'HandCompleted') {
            onHandCompleted(event.payload);
        }
        // TurnChanged needs no transient animation — the highlight/ring read
        // authoritative props once the reload below lands.

        opts.reload();
    }

    /**
     * Align the conductor with authoritative props (called on every prop
     * refresh). Keeps the dedup cursor and pot in sync so a poll that arrives
     * before/after an event can't double-animate or desync.
     */
    function syncFromProps(
        handId: number | null,
        actionSeq: number,
        potAmount: number,
    ): void {
        if (handId == null) {
            currentHandId = 0;
            lastSeq = 0;
            syncPot(0);

            return;
        }

        if (handId !== currentHandId) {
            currentHandId = handId;
            lastSeq = actionSeq;
        } else {
            lastSeq = Math.max(lastSeq, actionSeq);
        }

        syncPot(potAmount);
    }

    function cleanup(): void {
        if (potRaf !== null) {
            cancelAnimationFrame(potRaf);
        }

        fly.cancelAll();
        floatingLabels.value = [];
    }

    return {
        displayPot,
        potBounce,
        floatingLabels,
        dealing,
        handleEvent,
        syncFromProps,
        cleanup,
    };
}
