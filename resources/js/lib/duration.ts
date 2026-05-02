/**
 * Format an elapsed duration as "23m", "1u 4m", etc. Anything under a minute
 * shows as seconds; over an hour splits into hours + minutes.
 */
export function formatDuration(milliseconds: number): string {
    if (!Number.isFinite(milliseconds) || milliseconds < 0) {
        return '—';
    }
    const totalSeconds = Math.floor(milliseconds / 1000);
    if (totalSeconds < 60) {
        return `${totalSeconds}s`;
    }
    const totalMinutes = Math.floor(totalSeconds / 60);
    if (totalMinutes < 60) {
        return `${totalMinutes}m`;
    }
    const hours = Math.floor(totalMinutes / 60);
    const minutes = totalMinutes % 60;
    return minutes === 0 ? `${hours}u` : `${hours}u ${minutes}m`;
}

export function durationBetween(start: string, end: string | null): number {
    const startMs = new Date(start).getTime();
    const endMs = end ? new Date(end).getTime() : Date.now();
    return endMs - startMs;
}

/**
 * Stopwatch-style format with seconds: "0:42", "23:07", "1:04:09".
 */
export function formatStopwatch(milliseconds: number): string {
    const totalSeconds = Math.max(0, Math.floor(milliseconds / 1000));
    const hours = Math.floor(totalSeconds / 3600);
    const minutes = Math.floor((totalSeconds % 3600) / 60);
    const seconds = totalSeconds % 60;
    const ss = seconds.toString().padStart(2, '0');
    if (hours > 0) {
        const mm = minutes.toString().padStart(2, '0');
        return `${hours}:${mm}:${ss}`;
    }
    return `${minutes}:${ss}`;
}
