import { ref } from 'vue';

// Smoothed offset between the server clock and this client's clock (ms).
// Lets the countdown ring use absolute server `turn_deadline` timestamps
// without drifting when the client clock is off.
const skew = ref(0);
let primed = false;

/**
 * Feed a server timestamp (from any event's `server_now`) to refine the skew.
 */
export function syncServerClock(serverNowIso: string | null | undefined): void {
    if (!serverNowIso) {
        return;
    }

    const serverMs = Date.parse(serverNowIso);

    if (Number.isNaN(serverMs)) {
        return;
    }

    const sample = serverMs - Date.now();
    // First sample sets the baseline; subsequent ones are smoothed (EWMA).
    skew.value = primed ? skew.value * 0.8 + sample * 0.2 : sample;
    primed = true;
}

/** Current time on the server's clock, in ms. */
export function serverNow(): number {
    return Date.now() + skew.value;
}

export function useServerClock() {
    return { syncServerClock, serverNow };
}
