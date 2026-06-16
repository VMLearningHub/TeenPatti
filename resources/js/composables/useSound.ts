import { useStorage } from '@vueuse/core';

// Persisted mute preference, shared across the app.
const muted = useStorage('tp-muted', false);

let ctx: AudioContext | null = null;

function ensureContext(): AudioContext | null {
    if (typeof window === 'undefined') {
        return null;
    }

    if (!ctx) {
        const Ctor =
            window.AudioContext ??
            (window as unknown as { webkitAudioContext?: typeof AudioContext })
                .webkitAudioContext;

        if (!Ctor) {
            return null;
        }

        ctx = new Ctor();
    }

    return ctx;
}

/**
 * Synthesized sound cues via the Web Audio API — no audio assets required, so
 * nothing to ship or 404 on. Gated behind a user-gesture unlock (browser
 * autoplay policy) and the persisted mute toggle.
 */
export function useSound() {
    /** Resume the audio context. Call from a real user gesture (e.g. a click). */
    function unlock(): void {
        const c = ensureContext();

        if (c && c.state === 'suspended') {
            c.resume().catch(() => {});
        }
    }

    /** A short ascending victory arpeggio. */
    function playWin(): void {
        if (muted.value) {
            return;
        }

        const c = ensureContext();

        if (!c || c.state !== 'running') {
            return;
        } // not unlocked yet — skip silently

        const now = c.currentTime;
        const notes = [523.25, 659.25, 783.99, 1046.5]; // C5 E5 G5 C6
        notes.forEach((freq, i) => {
            const osc = c.createOscillator();
            const gain = c.createGain();
            osc.type = 'triangle';
            osc.frequency.value = freq;
            const t = now + i * 0.09;
            gain.gain.setValueAtTime(0, t);
            gain.gain.linearRampToValueAtTime(0.18, t + 0.02);
            gain.gain.exponentialRampToValueAtTime(0.0001, t + 0.35);
            osc.connect(gain).connect(c.destination);
            osc.start(t);
            osc.stop(t + 0.4);
        });
    }

    function toggleMute(): void {
        muted.value = !muted.value;
    }

    return { muted, unlock, playWin, toggleMute };
}
