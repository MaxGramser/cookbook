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
        for (const [id, t] of timers.value) {
            if (frozenRemaining.has(id)) {
                continue;
            }
            if (!t.finished && now.value >= t.endsAt && !beeped.has(id)) {
                beeped.add(id);
                t.finished = true;
                fireAlarm();
            }
        }
    }

    return { timers, remaining, start, dismiss, pauseAll, resumeAll, checkForFinished };
}

let audioCtx: AudioContext | null = null;

function fireAlarm(): void {
    if (typeof navigator !== 'undefined' && 'vibrate' in navigator) {
        navigator.vibrate([200, 120, 200, 120, 200]);
    }
    try {
        if (audioCtx === null) {
            audioCtx =
                new (window.AudioContext ||
                    (window as unknown as { webkitAudioContext: typeof AudioContext }).webkitAudioContext)();
        }
        for (let i = 0; i < 3; i++) {
            window.setTimeout(() => emitTone(880, 0.18), i * 240);
        }
    } catch {
        // AudioContext not allowed without prior gesture; silent fallback.
    }
}

function emitTone(frequency: number, durationSec: number): void {
    if (audioCtx === null) {
        return;
    }
    const osc = audioCtx.createOscillator();
    const gain = audioCtx.createGain();
    osc.type = 'sine';
    osc.frequency.value = frequency;
    osc.connect(gain);
    gain.connect(audioCtx.destination);
    gain.gain.setValueAtTime(0.25, audioCtx.currentTime);
    gain.gain.exponentialRampToValueAtTime(0.001, audioCtx.currentTime + durationSec);
    osc.start();
    osc.stop(audioCtx.currentTime + durationSec);
}
