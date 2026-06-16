<script setup lang="ts">
import { Head, router, usePage } from '@inertiajs/vue3';
import { Volume2, VolumeX } from 'lucide-vue-next';
import { computed, onMounted, onUnmounted, ref, watch } from 'vue';
import CountdownRing from '@/components/Game/CountdownRing.vue';
import PlayingCard from '@/components/Game/PlayingCard.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { useGameChannel } from '@/composables/useGameChannel';
import { useReducedMotion } from '@/composables/useReducedMotion';
import { useSound } from '@/composables/useSound';
import { useTableAnimations } from '@/composables/useTableAnimations';
import type { FloatingLabelItem } from '@/composables/useTableAnimations';
import { useTableGeometry } from '@/composables/useTableGeometry';
import type { Card, HandState, PlayerInfo, TableInfo } from '@/types/game';

defineOptions({
    layout: {
        breadcrumbs: [
            { title: 'Lobby', href: '/lobby' },
            { title: 'Table', href: '#' },
        ],
    },
});

const props = defineProps<{
    table: TableInfo;
    hand: HandState | null;
    my_cards: Card[] | null;
    my_legal_range: [number, number] | null;
    me: { id: number; name: string; wallet_balance: string };
}>();

const betAmount = ref<number>(Number(props.table.boot_amount));

// Flash error coming back from the server (e.g. invalid chaal amount).
const page = usePage();
const actionError = ref<string | null>(null);
watch(
    () => (page.props.flash as { error?: string } | undefined)?.error,
    (msg) => {
        if (msg) {
            actionError.value = msg;
        }
    },
    { immediate: true },
);

// Keep the bet input within the legal chaal range so it's hard to send a bad value.
watch(
    () => props.my_legal_range,
    (range) => {
        if (!range) {
            return;
        }

        const [low, high] = range;

        if (betAmount.value < low) {
            betAmount.value = low;
        }

        if (betAmount.value > high) {
            betAmount.value = high;
        }
    },
    { immediate: true },
);

const myPlayer = computed(
    () => props.hand?.players.find((p) => p.user_id === props.me.id) ?? null,
);

const isMyTurn = computed(
    () =>
        props.hand?.current_turn_seat === myPlayer.value?.seat_position &&
        !!myPlayer.value &&
        !myPlayer.value.has_packed &&
        props.hand?.status === 'betting',
);

const isSideshowTarget = computed(
    () => props.hand?.sideshow_target_id === props.me.id,
);

const activeCount = computed(
    () => props.hand?.players.filter((p) => !p.has_packed).length ?? 0,
);

const canShow = computed(() => activeCount.value === 2 && isMyTurn.value);

function nameForUser(userId: number | null | undefined): string {
    if (!userId) {
        return '—';
    }

    return (
        props.table.table_players.find((tp) => tp.user_id === userId)?.user
            .name ?? '—'
    );
}

// Human-readable result of a completed hand.
const resultText = computed(() => {
    const h = props.hand;

    if (!h || h.status !== 'completed') {
        return '';
    }

    if (h.winner_user_id) {
        return `${nameForUser(h.winner_user_id)} wins ₹${h.pot_amount}`;
    }

    const winners = h.players
        .filter((p) => p.is_winner)
        .map((p) => nameForUser(p.user_id));

    if (winners.length > 1) {
        return `Split pot: ${winners.join(' & ')} share ₹${h.pot_amount}`;
    }

    return 'Hand complete';
});

const seatedUsers = computed(() => {
    const seats: Record<number, TableInfo['table_players'][number]> = {};

    for (const tp of props.table.table_players) {
        seats[tp.seat_position] = tp;
    }

    return seats;
});

function getPlayerForSeat(seat: number): PlayerInfo | undefined {
    return props.hand?.players.find((p) => p.seat_position === seat);
}

// Position of a seat (and anything anchored to it) around the oval.
function seatStyle(seat: number): { left: string; top: string } {
    const angle =
        2 * Math.PI * ((seat - 1) / props.table.max_players) - Math.PI / 2;

    return {
        left: `${50 + 48 * Math.cos(angle)}%`,
        top: `${50 + 42 * Math.sin(angle)}%`,
    };
}

function seatForUser(userId: number): number | null {
    const hp = props.hand?.players.find((p) => p.user_id === userId);

    if (hp) {
        return hp.seat_position;
    }

    const tp = props.table.table_players.find((t) => t.user_id === userId);

    return tp ? tp.seat_position : null;
}

// ---- Animation layer ----------------------------------------------------

const geometry = useTableGeometry();
const sound = useSound();
const muted = sound.muted;
const { shouldAnimate } = useReducedMotion();

// On any Echo event, refresh the authoritative Inertia props (debounced) so the
// resting state catches up while the transient animation plays.
let reloadTimer: ReturnType<typeof setTimeout> | null = null;
function reload(): void {
    if (reloadTimer) {
        return;
    }

    reloadTimer = setTimeout(() => {
        reloadTimer = null;
        router.reload({
            only: ['hand', 'my_cards', 'my_legal_range', 'me', 'table'],
        });
    }, 120);
}

const {
    displayPot,
    potBounce,
    floatingLabels,
    handleEvent,
    syncFromProps,
    cleanup,
} = useTableAnimations({
    geometry,
    reload,
    sound,
    shouldAnimate,
    seatForUser,
});

// Subscribe to the table channel (client only — guards the SSR build).
if (!import.meta.env.SSR) {
    useGameChannel(props.table.code, handleEvent);
}

// Reconcile the conductor with authoritative props on every refresh.
watch(
    () => props.hand,
    (h) =>
        syncFromProps(
            h?.id ?? null,
            h?.action_seq ?? 0,
            h ? Number(h.pot_amount) : 0,
        ),
    { immediate: true },
);

// Re-trigger the pot bounce whenever the pot grows.
const potBouncing = ref(false);
watch(potBounce, () => {
    potBouncing.value = false;
    requestAnimationFrame(() => {
        potBouncing.value = true;
        setTimeout(() => (potBouncing.value = false), 420);
    });
});

const dealerStyle = computed(() =>
    props.hand
        ? seatStyle(props.hand.dealer_seat)
        : { left: '50%', top: '50%' },
);

const labelToneClass: Record<FloatingLabelItem['tone'], string> = {
    bet: 'bg-amber-300 text-amber-950',
    fold: 'bg-rose-500 text-white',
    see: 'bg-sky-400 text-sky-950',
    show: 'bg-violet-400 text-violet-950',
    win: 'bg-emerald-400 text-emerald-950',
};

function startHand() {
    sound.unlock();
    router.post(
        `/table/${props.table.code}/start`,
        {},
        { preserveScroll: true },
    );
}

function act(action: string, data: Record<string, unknown> = {}) {
    sound.unlock();
    actionError.value = null;
    router.post(
        `/table/${props.table.code}/action`,
        { action, ...data },
        { preserveScroll: true },
    );
}

function chaal() {
    actionError.value = null;
    const range = props.my_legal_range;

    if (range && (betAmount.value < range[0] || betAmount.value > range[1])) {
        actionError.value = `Chaal must be between ₹${range[0]} and ₹${range[1]}.`;

        return;
    }

    act('chaal', { amount: betAmount.value });
}

function leave() {
    router.post(`/table/${props.table.code}/leave`);
}

// Safety-net poll (events drive most refreshes; this heals dropped sockets).
let pollTimer: ReturnType<typeof setInterval> | null = null;
onMounted(() => {
    pollTimer = setInterval(() => {
        router.reload({
            only: ['hand', 'my_cards', 'my_legal_range', 'me', 'table'],
        });
    }, 8000);
});
onUnmounted(() => {
    if (pollTimer) {
        clearInterval(pollTimer);
    }

    if (reloadTimer) {
        clearTimeout(reloadTimer);
    }

    cleanup();
});
</script>

<template>
    <Head :title="`Table ${table.code}`" />

    <div class="flex flex-col gap-4 p-4">
        <div class="flex items-center justify-between">
            <div>
                <h1 class="text-xl font-semibold">
                    {{ table.name }}
                    <span
                        class="ml-2 font-mono text-sm text-muted-foreground"
                        >{{ table.code }}</span
                    >
                </h1>
                <div class="text-sm text-muted-foreground">
                    Boot ₹{{ table.boot_amount }} • Min ₹{{ table.min_bet }} •
                    Max ₹{{ table.max_bet }}
                </div>
            </div>
            <div class="flex items-center gap-3">
                <div class="text-sm">
                    Wallet:
                    <span class="font-semibold">₹{{ me.wallet_balance }}</span>
                </div>
                <button
                    type="button"
                    class="rounded-md border p-2 text-muted-foreground transition-colors hover:text-foreground"
                    :title="muted ? 'Unmute' : 'Mute'"
                    @click="sound.toggleMute()"
                >
                    <VolumeX v-if="muted" class="size-4" />
                    <Volume2 v-else class="size-4" />
                </button>
                <Button variant="outline" size="sm" @click="leave"
                    >Leave</Button
                >
            </div>
        </div>

        <!-- Table felt -->
        <div
            class="relative mx-auto aspect-video w-full max-w-4xl rounded-[40%/30%] bg-emerald-900 shadow-inner ring-8 ring-amber-900"
        >
            <!-- Pot -->
            <div
                v-if="hand"
                class="absolute inset-0 flex flex-col items-center justify-center text-center"
            >
                <div class="text-xs tracking-wider text-amber-200 uppercase">
                    Pot
                </div>
                <div
                    :ref="
                        (el) =>
                            (geometry.potEl.value = el as HTMLElement | null)
                    "
                    class="text-3xl font-bold text-amber-100 [font-variant-numeric:tabular-nums]"
                    :class="{ 'tp-animate-pot-bounce': potBouncing }"
                >
                    ₹{{ displayPot.toFixed(2) }}
                </div>
                <div class="mt-1 text-xs text-emerald-200">
                    Stake ₹{{ hand.current_stake }} • Status: {{ hand.status }}
                </div>
                <div
                    v-if="hand.status === 'completed'"
                    class="mt-3 flex flex-col items-center gap-1"
                >
                    <div
                        class="rounded-lg bg-amber-400/90 px-4 py-2 text-center text-amber-950 shadow-lg"
                    >
                        <div
                            class="text-xs font-semibold tracking-wider uppercase"
                        >
                            🏆 Hand Over
                        </div>
                        <div class="text-lg font-bold">{{ resultText }}</div>
                    </div>
                </div>
            </div>
            <div
                v-else
                class="absolute inset-0 flex flex-col items-center justify-center"
            >
                <div class="text-emerald-100">Waiting to start</div>
                <Button
                    class="mt-3"
                    :disabled="table.table_players.length < 2"
                    @click="startHand"
                    >Start Hand</Button
                >
            </div>

            <!-- Seats positioned around oval -->
            <template v-for="seat in table.max_players" :key="seat">
                <div
                    class="absolute -translate-x-1/2 -translate-y-1/2"
                    :style="seatStyle(seat)"
                >
                    <div
                        :ref="
                            (el) =>
                                geometry.registerSeat(
                                    seat,
                                    el as Element | null,
                                )
                        "
                        class="relative flex w-32 flex-col items-center rounded-xl border-2 p-2 text-center transition-all"
                        :class="{
                            'tp-animate-golden-pulse border-amber-300 bg-amber-900/60 text-amber-50':
                                hand &&
                                hand.current_turn_seat === seat &&
                                !getPlayerForSeat(seat)?.has_packed,
                            'border-zinc-500 bg-zinc-900/70 text-zinc-200':
                                !hand || hand.current_turn_seat !== seat,
                            'opacity-50': getPlayerForSeat(seat)?.has_packed,
                            'tp-animate-winner-glow border-emerald-400':
                                getPlayerForSeat(seat)?.is_winner,
                            'group cursor-default hover:-translate-y-1 hover:scale-105':
                                !seatedUsers[seat],
                        }"
                    >
                        <!-- Countdown ring for the active player -->
                        <CountdownRing
                            :deadline="hand?.turn_deadline ?? null"
                            :started-at="hand?.turn_started_at ?? null"
                            :active="
                                !!hand &&
                                hand.current_turn_seat === seat &&
                                hand.status === 'betting'
                            "
                        />

                        <div
                            v-if="seatedUsers[seat]"
                            class="text-sm font-semibold"
                        >
                            {{ seatedUsers[seat].user.name }}
                        </div>
                        <template v-else>
                            <div class="text-xs text-zinc-400">Empty Seat</div>
                            <div
                                class="pointer-events-none absolute -top-7 left-1/2 -translate-x-1/2 rounded bg-zinc-800 px-2 py-0.5 text-[10px] text-zinc-200 opacity-0 shadow transition-opacity group-hover:opacity-100"
                            >
                                Join Table
                            </div>
                        </template>

                        <div v-if="seatedUsers[seat]" class="text-xs">
                            ₹{{ seatedUsers[seat].user.wallet_balance }}
                        </div>

                        <!-- Cards for this seat -->
                        <div
                            v-if="getPlayerForSeat(seat)"
                            class="mt-1 flex gap-1"
                        >
                            <PlayingCard
                                v-for="(c, idx) in getPlayerForSeat(seat)!
                                    .cards ?? [null, null, null]"
                                :key="idx"
                                :card="c"
                                :flip-delay="idx * 120"
                                size="sm"
                            />
                        </div>

                        <div v-if="getPlayerForSeat(seat)" class="mt-1 text-xs">
                            <span
                                v-if="getPlayerForSeat(seat)!.has_packed"
                                class="text-rose-400"
                                >Packed</span
                            >
                            <span
                                v-else-if="getPlayerForSeat(seat)!.is_blind"
                                class="text-sky-300"
                                >Blind</span
                            >
                            <span v-else class="text-emerald-300">Seen</span>
                        </div>
                    </div>
                </div>
            </template>

            <!-- Sliding dealer button -->
            <div
                v-if="hand"
                class="pointer-events-none absolute z-20 -translate-x-1/2 translate-y-6 transition-all duration-500 ease-out"
                :style="dealerStyle"
            >
                <div
                    class="rounded-full bg-amber-300 px-2 text-xs font-bold text-amber-900 shadow"
                >
                    D
                </div>
            </div>

            <!-- Floating action labels -->
            <div
                v-for="label in floatingLabels"
                :key="label.id"
                class="tp-animate-float-up-fade pointer-events-none absolute z-50 -translate-x-1/2 -translate-y-8"
                :style="seatStyle(label.seat)"
            >
                <span
                    class="rounded-full px-2 py-0.5 text-xs font-bold shadow"
                    :class="labelToneClass[label.tone]"
                >
                    {{ label.text }}
                </span>
            </div>

            <!-- Overlay layer for flying chips/cards -->
            <div
                :ref="
                    (el) =>
                        (geometry.overlayEl.value = el as HTMLElement | null)
                "
                class="pointer-events-none absolute inset-0 z-40 overflow-hidden"
            />
        </div>

        <!-- Action panel -->
        <div
            v-if="
                hand &&
                myPlayer &&
                !myPlayer.has_packed &&
                hand.status === 'betting'
            "
            class="rounded-lg border bg-card p-4"
        >
            <div class="mb-3 text-sm">
                <span v-if="isMyTurn" class="font-semibold text-emerald-600"
                    >Your turn.</span
                >
                <span v-else class="text-muted-foreground"
                    >Waiting for other players…</span
                >
                <span v-if="myPlayer.is_blind" class="ml-2 text-sky-600"
                    >You are playing BLIND.</span
                >
                <span v-else class="ml-2 text-emerald-600"
                    >You have SEEN your cards.</span
                >
            </div>

            <div
                v-if="my_legal_range"
                class="mb-2 text-xs text-muted-foreground"
            >
                Legal chaal range: ₹{{ my_legal_range[0] }} – ₹{{
                    my_legal_range[1]
                }}
            </div>

            <div
                v-if="actionError"
                class="mb-3 flex items-start justify-between gap-3 rounded-md border border-rose-400/50 bg-rose-50 px-3 py-2 text-sm text-rose-700 dark:bg-rose-950/40 dark:text-rose-300"
            >
                <span>{{ actionError }}</span>
                <button
                    type="button"
                    class="shrink-0 font-semibold hover:opacity-70"
                    @click="actionError = null"
                >
                    ✕
                </button>
            </div>

            <!-- Action buttons only appear on your turn; off-turn they're hidden
                 so a disabled "See Cards"/"Chaal"/etc. can't be mistaken for usable. -->
            <div v-if="isMyTurn" class="flex flex-wrap gap-2">
                <Button
                    v-if="myPlayer.is_blind"
                    variant="secondary"
                    class="transition-transform active:scale-95"
                    @click="act('see_cards')"
                >
                    See Cards
                </Button>
                <Button
                    variant="destructive"
                    class="transition-transform active:scale-95"
                    @click="act('pack')"
                    >Pack</Button
                >

                <div class="flex items-center gap-2">
                    <Input
                        v-model.number="betAmount"
                        type="number"
                        class="w-28"
                        :min="my_legal_range?.[0]"
                        :max="my_legal_range?.[1]"
                    />
                    <Button
                        class="transition-transform active:scale-95"
                        @click="chaal"
                        >Chaal</Button
                    >
                </div>

                <Button
                    v-if="!myPlayer.is_blind && activeCount >= 3"
                    variant="outline"
                    class="transition-transform active:scale-95"
                    @click="act('sideshow_request')"
                >
                    Sideshow
                </Button>
                <Button
                    v-if="canShow"
                    variant="default"
                    class="transition-transform active:scale-95"
                    @click="act('show')"
                    >Show</Button
                >
            </div>
            <div v-else class="text-sm text-muted-foreground">
                It's not your turn yet — actions will appear when it's your
                turn.
            </div>

            <div
                v-if="isSideshowTarget"
                class="mt-3 rounded border border-amber-400 bg-amber-50 p-3 text-sm dark:bg-amber-950/30"
            >
                <div class="mb-2 font-semibold">Sideshow requested!</div>
                <div class="flex gap-2">
                    <Button
                        size="sm"
                        @click="act('sideshow_respond', { accept: true })"
                        >Accept</Button
                    >
                    <Button
                        size="sm"
                        variant="outline"
                        @click="act('sideshow_respond', { accept: false })"
                        >Decline</Button
                    >
                </div>
            </div>
        </div>

        <!-- Start next hand -->
        <div
            v-if="!hand || hand.status === 'completed'"
            class="rounded-lg border bg-card p-4"
        >
            <Button
                :disabled="table.table_players.length < 2"
                @click="startHand"
                >Deal Next Hand</Button
            >
            <span
                v-if="table.table_players.length < 2"
                class="ml-3 text-sm text-muted-foreground"
                >Need 2+ players to start.</span
            >
        </div>

        <!-- My cards full view -->
        <div v-if="my_cards" class="rounded-lg border bg-card p-4">
            <div class="mb-2 text-sm font-semibold">Your hand</div>
            <div class="flex gap-2">
                <PlayingCard
                    v-for="(c, idx) in my_cards"
                    :key="idx"
                    :card="c"
                    :flip-delay="idx * 150"
                    size="lg"
                />
            </div>
        </div>
    </div>
</template>
