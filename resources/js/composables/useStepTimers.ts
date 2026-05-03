import { computed, ref, type Ref } from 'vue';

export type TimerState = {
    durationMs: number;
    endsAt: number;
    finished: boolean;
};

const MINUTE_PATTERN =
    /(\d+)(?:\s*[-–—]\s*\d+)?\s*min(?:uten|utes|uut)?\b/i;

export function detectTimerMinutes(text: string): number | null {
    const match = text.match(MINUTE_PATTERN);
    if (!match) {
        return null;
    }
    const minutes = Number.parseInt(match[1], 10);
    if (!Number.isFinite(minutes) || minutes < 1 || minutes > 240) {
        return null;
    }
    return minutes;
}

export function useStepTimers(now: Ref<number>) {
    const timers = ref<Map<number, TimerState>>(new Map());
    const beeped = new Set<number>();
    /** While a session is paused, each timer's remaining ms is frozen here. */
    const frozenRemaining = new Map<number, number>();

    function start(stepId: number, minutes: number): void {
        // Browsers gate the AudioContext behind a user gesture. Start press is
        // that gesture — unlock the context now and emit a brief confirmation
        // beep so the user knows audio will fire when the timer ends.
        armAudio();

        const durationMs = minutes * 60_000;
        timers.value = new Map(timers.value).set(stepId, {
            durationMs,
            endsAt: Date.now() + durationMs,
            finished: false,
        });
        beeped.delete(stepId);
        frozenRemaining.delete(stepId);
    }

    function dismiss(stepId: number): void {
        const next = new Map(timers.value);
        next.delete(stepId);
        timers.value = next;
        beeped.delete(stepId);
        frozenRemaining.delete(stepId);
    }

    function pauseAll(): void {
        const nowMs = Date.now();
        for (const [id, t] of timers.value) {
            if (t.finished) {
                continue;
            }
            frozenRemaining.set(id, Math.max(0, t.endsAt - nowMs));
        }
    }

    function resumeAll(): void {
        if (frozenRemaining.size === 0) {
            return;
        }
        const next = new Map(timers.value);
        const nowMs = Date.now();
        for (const [id, remainingMs] of frozenRemaining) {
            const t = next.get(id);
            if (t && !t.finished) {
                next.set(id, { ...t, endsAt: nowMs + remainingMs });
            }
        }
        timers.value = next;
        frozenRemaining.clear();
    }

    const remaining = computed(() => {
        const out = new Map<number, number>();
        for (const [id, t] of timers.value) {
            const frozen = frozenRemaining.get(id);
            out.set(id, frozen ?? Math.max(0, t.endsAt - now.value));
        }
        return out;
    });

    function checkForFinished(): void {
        let anyRunning = false;
        for (const [id, t] of timers.value) {
            if (frozenRemaining.has(id)) {
                continue;
            }
            if (t.finished) {
                continue;
            }
            anyRunning = true;
            if (now.value >= t.endsAt && !beeped.has(id)) {
                beeped.add(id);
                t.finished = true;
                fireAlarm();
            }
        }
        maybeTick(now.value, anyRunning);
    }

    return { timers, remaining, start, dismiss, pauseAll, resumeAll, checkForFinished };
}

let audioCtx: AudioContext | null = null;
let lastTickSecond = 0;

/** Soft once-per-second tick while any timer is running. Audible just enough
 *  to hint that the timer is alive without becoming annoying. */
function maybeTick(nowMs: number, anyRunning: boolean): void {
    if (!anyRunning) {
        return;
    }
    if (audioCtx === null || audioCtx.state !== 'running') {
        return;
    }
    const second = Math.floor(nowMs / 1000);
    if (second === lastTickSecond) {
        return;
    }
    lastTickSecond = second;
    emitTone({ frequency: 2200, durationSec: 0.012, gain: 0.008, type: 'sine' });
}

function getAudioCtx(): AudioContext | null {
    if (audioCtx !== null) {
        return audioCtx;
    }
    try {
        const Ctor =
            window.AudioContext ||
            (window as unknown as { webkitAudioContext: typeof AudioContext }).webkitAudioContext;
        audioCtx = new Ctor();
    } catch {
        return null;
    }
    return audioCtx;
}

/** Called on Start press (a real user gesture) to unlock the AudioContext and
 *  play a friendly 4-note ascending major arpeggio (C5-E5-G5-C6) so the user
 *  unambiguously hears that audio is armed. */
function armAudio(): void {
    const ctx = getAudioCtx();
    if (ctx === null) {
        return;
    }
    if (ctx.state === 'suspended') {
        ctx.resume().catch(() => {});
    }
    const melody: Array<{ frequency: number; offsetMs: number }> = [
        { frequency: 523.25, offsetMs: 0 },    // C5
        { frequency: 659.25, offsetMs: 130 },  // E5
        { frequency: 783.99, offsetMs: 260 },  // G5
        { frequency: 1046.5, offsetMs: 390 },  // C6
    ];
    for (const { frequency, offsetMs } of melody) {
        window.setTimeout(
            () => emitTone({ frequency, durationSec: 0.18, gain: 0.22, type: 'sine' }),
            offsetMs,
        );
    }
}

/** Loud, attention-grabbing alarm for when a timer finishes. Alternating
 *  two-tone square-wave pattern (think kitchen timer / smoke alarm). */
function fireAlarm(): void {
    if (typeof navigator !== 'undefined' && 'vibrate' in navigator) {
        navigator.vibrate([300, 120, 300, 120, 300, 120, 600]);
    }
    const ctx = getAudioCtx();
    if (ctx === null) {
        return;
    }
    if (ctx.state === 'suspended') {
        ctx.resume().catch(() => {});
    }
    // 4 alternating bursts: high-low-high-low. Square wave with sharp envelope.
    const pattern: Array<{ frequency: number; offsetMs: number }> = [
        { frequency: 1320, offsetMs: 0 },
        { frequency: 880, offsetMs: 280 },
        { frequency: 1320, offsetMs: 560 },
        { frequency: 880, offsetMs: 840 },
        { frequency: 1320, offsetMs: 1120 },
        { frequency: 880, offsetMs: 1400 },
    ];
    for (const { frequency, offsetMs } of pattern) {
        window.setTimeout(
            () => emitTone({ frequency, durationSec: 0.22, gain: 0.6, type: 'square' }),
            offsetMs,
        );
    }
}

type ToneOpts = { frequency: number; durationSec: number; gain: number; type: OscillatorType };

function emitTone({ frequency, durationSec, gain, type }: ToneOpts): void {
    const ctx = audioCtx;
    if (ctx === null) {
        return;
    }
    try {
        const osc = ctx.createOscillator();
        const env = ctx.createGain();
        osc.type = type;
        osc.frequency.value = frequency;
        osc.connect(env);
        env.connect(ctx.destination);
        const start = ctx.currentTime;
        // Quick attack then exponential decay → sharp, bell-like punch.
        env.gain.setValueAtTime(0.0001, start);
        env.gain.exponentialRampToValueAtTime(gain, start + 0.005);
        env.gain.exponentialRampToValueAtTime(0.0001, start + durationSec);
        osc.start(start);
        osc.stop(start + durationSec + 0.02);
    } catch {
        // Audio failed mid-flight; silent fallback rather than crashing the timer loop.
    }
}
