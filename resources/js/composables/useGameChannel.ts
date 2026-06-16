import { useConnectionStatus, useEcho } from '@laravel/echo-vue';
import type {
    GameEvent,
    HandCompletedPayload,
    HandStartedPayload,
    PlayerActedPayload,
    TurnChangedPayload,
} from '@/types/game';

/**
 * Subscribe to a table's private broadcast channel and forward the four game
 * events as a typed discriminated union. Channel lifecycle (subscribe/leave) is
 * handled by @laravel/echo-vue's useEcho across the component lifetime.
 *
 * Events are *triggers* only — Inertia props remain the source of truth.
 */
export function useGameChannel(
    tableCode: string,
    onEvent: (event: GameEvent) => void,
) {
    const channel = `table.${tableCode}`;

    // Custom broadcastAs() names are listened to with a leading dot.
    useEcho<HandStartedPayload>(
        channel,
        '.HandStarted',
        (payload) => onEvent({ type: 'HandStarted', payload }),
        [],
        'private',
    );
    useEcho<PlayerActedPayload>(
        channel,
        '.PlayerActed',
        (payload) => onEvent({ type: 'PlayerActed', payload }),
        [],
        'private',
    );
    useEcho<TurnChangedPayload>(
        channel,
        '.TurnChanged',
        (payload) => onEvent({ type: 'TurnChanged', payload }),
        [],
        'private',
    );
    useEcho<HandCompletedPayload>(
        channel,
        '.HandCompleted',
        (payload) => onEvent({ type: 'HandCompleted', payload }),
        [],
        'private',
    );

    const status = useConnectionStatus();

    return { status };
}
