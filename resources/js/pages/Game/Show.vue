<script setup lang="ts">
import { Head, router, usePage } from '@inertiajs/vue3';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { computed, onMounted, onUnmounted, ref, watch } from 'vue';

defineOptions({
    layout: {
        breadcrumbs: [
            { title: 'Lobby', href: '/lobby' },
            { title: 'Table', href: '#' },
        ],
    },
});

interface PlayerInfo {
    user_id: number;
    seat_position: number;
    is_blind: boolean;
    has_packed: boolean;
    is_winner: boolean;
    hand_rank: number | null;
    total_contributed: string;
    cards: Array<{ rank: string; suit: string }> | null;
}

interface HandState {
    id: number;
    status: string;
    pot_amount: string;
    current_stake: string;
    current_turn_seat: number | null;
    dealer_seat: number;
    sideshow_requester_id: number | null;
    sideshow_target_id: number | null;
    winner_user_id: number | null;
    players: PlayerInfo[];
}

interface TableInfo {
    id: number;
    code: string;
    name: string;
    boot_amount: string;
    min_bet: string;
    max_bet: string;
    max_players: number;
    status: string;
    table_players: Array<{
        user_id: number;
        seat_position: number;
        chips_on_table: string;
        is_active: boolean;
        user: { id: number; name: string; wallet_balance: string; avatar?: string };
    }>;
}

const props = defineProps<{
    table: TableInfo;
    hand: HandState | null;
    my_cards: Array<{ rank: string; suit: string }> | null;
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
        if (msg) actionError.value = msg;
    },
    { immediate: true },
);

// Keep the bet input within the legal chaal range so it's hard to send a bad value.
watch(
    () => props.my_legal_range,
    (range) => {
        if (!range) return;
        const [low, high] = range;
        if (betAmount.value < low) betAmount.value = low;
        if (betAmount.value > high) betAmount.value = high;
    },
    { immediate: true },
);

function chaal() {
    actionError.value = null;
    const range = props.my_legal_range;
    if (range && (betAmount.value < range[0] || betAmount.value > range[1])) {
        actionError.value = `Chaal must be between ₹${range[0]} and ₹${range[1]}.`;
        return;
    }
    act('chaal', { amount: betAmount.value });
}

const myPlayer = computed(() =>
    props.hand?.players.find((p) => p.user_id === props.me.id) ?? null,
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
    if (!userId) return '—';
    return props.table.table_players.find((tp) => tp.user_id === userId)?.user.name ?? '—';
}

// Human-readable result of a completed hand.
const resultText = computed(() => {
    const h = props.hand;
    if (!h || h.status !== 'completed') return '';
    if (h.winner_user_id) {
        return `${nameForUser(h.winner_user_id)} wins ₹${h.pot_amount}`;
    }
    // No single winner → pot was split between the remaining players.
    const winners = h.players.filter((p) => p.is_winner).map((p) => nameForUser(p.user_id));
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

function startHand() {
    router.post(`/table/${props.table.code}/start`, {}, { preserveScroll: true });
}

function act(action: string, data: Record<string, unknown> = {}) {
    actionError.value = null;
    router.post(`/table/${props.table.code}/action`, { action, ...data }, { preserveScroll: true });
}

function leave() {
    router.post(`/table/${props.table.code}/leave`);
}

const suitGlyph: Record<string, string> = { S: '♠', H: '♥', D: '♦', C: '♣' };
const suitClass: Record<string, string> = {
    S: 'text-zinc-100', H: 'text-rose-400', D: 'text-rose-400', C: 'text-zinc-100',
};

// Polling for state updates
let pollTimer: ReturnType<typeof setInterval> | null = null;
onMounted(() => {
    pollTimer = setInterval(() => {
        router.reload({ only: ['hand', 'my_cards', 'my_legal_range', 'me', 'table'] });
    }, 2500);
});
onUnmounted(() => {
    if (pollTimer) clearInterval(pollTimer);
});
</script>

<template>
    <Head :title="`Table ${table.code}`" />

    <div class="flex flex-col gap-4 p-4">
        <div class="flex items-center justify-between">
            <div>
                <h1 class="text-xl font-semibold">
                    {{ table.name }} <span class="ml-2 font-mono text-sm text-muted-foreground">{{ table.code }}</span>
                </h1>
                <div class="text-sm text-muted-foreground">
                    Boot ₹{{ table.boot_amount }} • Min ₹{{ table.min_bet }} • Max ₹{{ table.max_bet }}
                </div>
            </div>
            <div class="flex items-center gap-3">
                <div class="text-sm">Wallet: <span class="font-semibold">₹{{ me.wallet_balance }}</span></div>
                <Button variant="outline" size="sm" @click="leave">Leave</Button>
            </div>
        </div>

        <!-- Table felt -->
        <div class="relative mx-auto aspect-video w-full max-w-4xl rounded-[40%/30%] bg-emerald-900 shadow-inner ring-8 ring-amber-900">
            <!-- Pot -->
            <div v-if="hand" class="absolute inset-0 flex flex-col items-center justify-center text-center">
                <div class="text-amber-200 text-xs uppercase tracking-wider">Pot</div>
                <div class="text-3xl font-bold text-amber-100">₹{{ hand.pot_amount }}</div>
                <div class="mt-1 text-xs text-emerald-200">
                    Stake ₹{{ hand.current_stake }} • Status: {{ hand.status }}
                </div>
                <div v-if="hand.status === 'completed'" class="mt-3 flex flex-col items-center gap-1">
                    <div class="rounded-lg bg-amber-400/90 px-4 py-2 text-center text-amber-950 shadow-lg">
                        <div class="text-xs font-semibold uppercase tracking-wider">🏆 Hand Over</div>
                        <div class="text-lg font-bold">{{ resultText }}</div>
                    </div>
                </div>
            </div>
            <div v-else class="absolute inset-0 flex flex-col items-center justify-center">
                <div class="text-emerald-100">Waiting to start</div>
                <Button class="mt-3" :disabled="table.table_players.length < 2" @click="startHand">
                    Start Hand
                </Button>
            </div>

            <!-- Seats positioned around oval -->
            <template v-for="seat in table.max_players" :key="seat">
                <div
                    class="absolute -translate-x-1/2 -translate-y-1/2"
                    :style="{
                        left: `${50 + 48 * Math.cos(2 * Math.PI * ((seat - 1) / table.max_players) - Math.PI / 2)}%`,
                        top: `${50 + 42 * Math.sin(2 * Math.PI * ((seat - 1) / table.max_players) - Math.PI / 2)}%`,
                    }"
                >
                    <div
                        class="flex w-32 flex-col items-center rounded-xl border-2 p-2 text-center"
                        :class="{
                            'border-amber-300 bg-amber-900/60 text-amber-50': hand && hand.current_turn_seat === seat,
                            'border-zinc-500 bg-zinc-900/70 text-zinc-200': !hand || hand.current_turn_seat !== seat,
                            'opacity-50': getPlayerForSeat(seat)?.has_packed,
                            'ring-2 ring-emerald-400': getPlayerForSeat(seat)?.is_winner,
                        }"
                    >
                        <div v-if="seatedUsers[seat]" class="text-sm font-semibold">
                            {{ seatedUsers[seat].user.name }}
                        </div>
                        <div v-else class="text-xs text-zinc-400">Empty Seat</div>

                        <div v-if="seatedUsers[seat]" class="text-xs">
                            ₹{{ seatedUsers[seat].chips_on_table }}
                        </div>

                        <!-- Cards for this seat -->
                        <div v-if="getPlayerForSeat(seat)" class="mt-1 flex gap-1">
                            <div
                                v-for="(c, idx) in getPlayerForSeat(seat)!.cards ?? [null, null, null]"
                                :key="idx"
                                class="flex h-10 w-7 items-center justify-center rounded border bg-zinc-800 text-sm font-bold"
                                :class="c ? [suitClass[c.suit], 'bg-zinc-900'] : 'bg-rose-800'"
                            >
                                <template v-if="c">
                                    {{ c.rank }}<span class="text-xs">{{ suitGlyph[c.suit] }}</span>
                                </template>
                                <template v-else>•</template>
                            </div>
                        </div>

                        <div v-if="getPlayerForSeat(seat)" class="mt-1 text-xs">
                            <span v-if="getPlayerForSeat(seat)!.has_packed" class="text-rose-400">Packed</span>
                            <span v-else-if="getPlayerForSeat(seat)!.is_blind" class="text-sky-300">Blind</span>
                            <span v-else class="text-emerald-300">Seen</span>
                        </div>
                        <div v-if="hand && hand.dealer_seat === seat" class="mt-1 inline-block rounded-full bg-amber-300 px-2 text-xs font-bold text-amber-900">
                            D
                        </div>
                    </div>
                </div>
            </template>
        </div>

        <!-- Action panel -->
        <div v-if="hand && myPlayer && !myPlayer.has_packed && hand.status === 'betting'" class="rounded-lg border bg-card p-4">
            <div class="mb-3 text-sm">
                <span v-if="isMyTurn" class="font-semibold text-emerald-600">Your turn.</span>
                <span v-else class="text-muted-foreground">Waiting for other players…</span>
                <span v-if="myPlayer.is_blind" class="ml-2 text-sky-600">You are playing BLIND.</span>
                <span v-else class="ml-2 text-emerald-600">You have SEEN your cards.</span>
            </div>

            <div v-if="my_legal_range" class="mb-2 text-xs text-muted-foreground">
                Legal chaal range: ₹{{ my_legal_range[0] }} – ₹{{ my_legal_range[1] }}
            </div>

            <div
                v-if="actionError"
                class="mb-3 flex items-start justify-between gap-3 rounded-md border border-rose-400/50 bg-rose-50 px-3 py-2 text-sm text-rose-700 dark:bg-rose-950/40 dark:text-rose-300"
            >
                <span>{{ actionError }}</span>
                <button type="button" class="shrink-0 font-semibold hover:opacity-70" @click="actionError = null">✕</button>
            </div>

            <div class="flex flex-wrap gap-2">
                <Button v-if="myPlayer.is_blind" variant="secondary" :disabled="!isMyTurn" @click="act('see_cards')">See Cards</Button>
                <Button variant="destructive" :disabled="!isMyTurn" @click="act('pack')">Pack</Button>

                <div class="flex items-center gap-2">
                    <Input v-model.number="betAmount" type="number" class="w-28" :min="my_legal_range?.[0]" :max="my_legal_range?.[1]" />
                    <Button :disabled="!isMyTurn" @click="chaal">Chaal</Button>
                </div>

                <Button v-if="!myPlayer.is_blind && activeCount >= 3" variant="outline" :disabled="!isMyTurn" @click="act('sideshow_request')">
                    Sideshow
                </Button>
                <Button v-if="canShow" variant="default" @click="act('show')">Show</Button>
            </div>

            <div v-if="isSideshowTarget" class="mt-3 rounded border border-amber-400 bg-amber-50 p-3 text-sm dark:bg-amber-950/30">
                <div class="mb-2 font-semibold">Sideshow requested!</div>
                <div class="flex gap-2">
                    <Button size="sm" @click="act('sideshow_respond', { accept: true })">Accept</Button>
                    <Button size="sm" variant="outline" @click="act('sideshow_respond', { accept: false })">Decline</Button>
                </div>
            </div>
        </div>

        <!-- Start next hand -->
        <div v-if="!hand || hand.status === 'completed'" class="rounded-lg border bg-card p-4">
            <Button :disabled="table.table_players.length < 2" @click="startHand">
                Deal Next Hand
            </Button>
            <span v-if="table.table_players.length < 2" class="ml-3 text-sm text-muted-foreground">
                Need 2+ players to start.
            </span>
        </div>

        <!-- My cards full view -->
        <div v-if="my_cards" class="rounded-lg border bg-card p-4">
            <div class="mb-2 text-sm font-semibold">Your hand</div>
            <div class="flex gap-2">
                <div
                    v-for="(c, idx) in my_cards"
                    :key="idx"
                    class="flex h-20 w-14 items-center justify-center rounded-md border-2 bg-zinc-900 text-2xl font-bold"
                    :class="suitClass[c.suit]"
                >
                    {{ c.rank }}<span>{{ suitGlyph[c.suit] }}</span>
                </div>
            </div>
        </div>
    </div>
</template>
