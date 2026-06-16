/**
 * Fires a celebratory confetti burst. canvas-confetti is dynamically imported
 * so it stays out of the main bundle until the first win.
 */
export async function fireConfetti(): Promise<void> {
    if (typeof window === 'undefined') {
        return;
    }

    const confetti = (await import('canvas-confetti')).default;

    confetti({
        particleCount: 90,
        spread: 75,
        startVelocity: 45,
        origin: { y: 0.6 },
    });
    confetti({
        particleCount: 50,
        angle: 60,
        spread: 60,
        origin: { x: 0, y: 0.7 },
    });
    confetti({
        particleCount: 50,
        angle: 120,
        spread: 60,
        origin: { x: 1, y: 0.7 },
    });
}
