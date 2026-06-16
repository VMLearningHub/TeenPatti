// Shared game types: the Inertia prop shapes (source of truth) and the
// real-time broadcast event payloads (animation triggers).

export interface Card {
    rank: string;
    suit: string;
}

export interface PlayerInfo {
    user_id: number;
    seat_position: number;
    is_blind: boolean;
    has_packed: boolean;
    is_winner: boolean;
    hand_rank: number | null;
    total_contributed: string;
    cards: Card[] | null;
}

export interface RecentAction {
    id: number;
    user_id: number;
    action: string;
    amount: string;
    created_at: string | null;
}

export interface HandState {
    id: number;
    status: string;
    pot_amount: string;
    current_stake: string;
    current_turn_seat: number | null;
    dealer_seat: number;
    previous_dealer_seat: number | null;
    sideshow_requester_id: number | null;
    sideshow_target_id: number | null;
    winner_user_id: number | null;
    deal_order: Array<{ seat_position: number; user_id: number }> | null;
    action_seq: number;
    turn_started_at: string | null;
    turn_deadline: string | null;
    server_now: string | null;
    recent_actions: RecentAction[];
    players: PlayerInfo[];
}

export interface TablePlayerInfo {
    user_id: number;
    seat_position: number;
    chips_on_table: string;
    is_active: boolean;
    user: { id: number; name: string; wallet_balance: string; avatar?: string };
}

export interface TableInfo {
    id: number;
    code: string;
    name: string;
    boot_amount: string;
    min_bet: string;
    max_bet: string;
    max_players: number;
    status: string;
    table_players: TablePlayerInfo[];
}

// ---- Broadcast event payloads (must mirror GameService broadcastWith arrays) ----

interface BaseEventPayload {
    hand_id: number;
    action_seq: number;
    server_now: string | null;
}

export interface HandStartedPayload extends BaseEventPayload {
    dealer_seat: number;
    previous_dealer_seat: number | null;
    current_turn_seat: number | null;
    turn_started_at: string | null;
    turn_deadline: string | null;
    pot_amount: string;
    current_stake: string;
    status: string;
    deal_order: Array<{ seat_position: number; user_id: number }> | null;
    players: Array<{
        user_id: number;
        seat_position: number;
        is_blind: boolean;
        has_packed: boolean;
        total_contributed: string;
    }>;
}

export interface PlayerActedPayload extends BaseEventPayload {
    action: string;
    user_id: number;
    seat_position: number;
    amount: string;
    pot_delta: string;
    pot_amount: string;
    current_stake: string;
    was_blind: boolean;
}

export interface TurnChangedPayload extends BaseEventPayload {
    current_turn_seat: number | null;
    turn_started_at: string | null;
    turn_deadline: string | null;
}

export interface HandCompletedPayload extends BaseEventPayload {
    winner_user_id: number | null;
    is_split: boolean;
    pot_amount: string;
    payouts: Array<{ user_id: number; amount: string }>;
    revealed: Array<{
        user_id: number;
        seat_position: number;
        hand_rank: number;
        cards: Card[];
    }>;
}

export type GameEvent =
    | { type: 'HandStarted'; payload: HandStartedPayload }
    | { type: 'PlayerActed'; payload: PlayerActedPayload }
    | { type: 'TurnChanged'; payload: TurnChangedPayload }
    | { type: 'HandCompleted'; payload: HandCompletedPayload };
