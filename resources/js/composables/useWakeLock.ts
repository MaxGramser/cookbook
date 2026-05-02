import { onBeforeUnmount, onMounted } from 'vue';

/**
 * Keep the screen awake while a cook session is active. Re-acquires after the
 * tab is backgrounded (browsers drop wake locks on visibility change).
 */
export function useWakeLock(enabled: () => boolean): void {
    let sentinel: WakeLockSentinel | null = null;

    async function acquire(): Promise<void> {
        if (!enabled() || !('wakeLock' in navigator)) {
            return;
        }
        try {
            sentinel = await (navigator as Navigator & { wakeLock: WakeLock }).wakeLock.request(
                'screen',
            );
        } catch {
            // Permission denied or feature unsupported — silent fallback.
        }
    }

    function release(): void {
        sentinel?.release().catch(() => {});
        sentinel = null;
    }

    function onVisibility(): void {
        if (document.visibilityState === 'visible') {
            void acquire();
        }
    }

    onMounted(() => {
        void acquire();
        document.addEventListener('visibilitychange', onVisibility);
    });

    onBeforeUnmount(() => {
        document.removeEventListener('visibilitychange', onVisibility);
        release();
    });
}
