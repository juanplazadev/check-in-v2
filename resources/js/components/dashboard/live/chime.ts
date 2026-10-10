let context: AudioContext | null = null;

/**
 * Browsers keep audio suspended until the page gets a user gesture, so the context is created
 * (or resumed) on the first click or key press anywhere on the dashboard.
 */
export function unlockChime(): void {
    try {
        context ??= new AudioContext();

        if (context.state === 'suspended') {
            void context.resume();
        }
    } catch {
        context = null;
    }
}

/** A short two-note chime, synthesised so there is no audio file to ship. */
export function playChime(): void {
    if (context === null || context.state !== 'running') {
        return;
    }

    const start = context.currentTime;

    [880, 1318.5].forEach((frequency, index) => {
        const oscillator = context!.createOscillator();
        const gain = context!.createGain();
        const at = start + index * 0.16;

        oscillator.type = 'sine';
        oscillator.frequency.value = frequency;
        gain.gain.setValueAtTime(0.0001, at);
        gain.gain.exponentialRampToValueAtTime(0.25, at + 0.02);
        gain.gain.exponentialRampToValueAtTime(0.0001, at + 0.5);

        oscillator.connect(gain).connect(context!.destination);
        oscillator.start(at);
        oscillator.stop(at + 0.55);
    });
}
