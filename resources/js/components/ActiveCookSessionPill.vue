<script setup lang="ts">
import { Link, usePage } from '@inertiajs/vue3';
import { ChefHat, Pause } from 'lucide-vue-next';
import { computed, onBeforeUnmount, onMounted, ref } from 'vue';
import { formatStopwatch } from '@/lib/duration';
import { show as showCookSession } from '@/routes/cook';
import type { ActiveCookSession } from '@/types/global';

const page = usePage();

const activeSession = computed<ActiveCookSession | null>(() => {
    return (page.props.activeCookSession as ActiveCookSession | null) ?? null;
});

const isOnSessionPage = computed<boolean>(() => {
    if (activeSession.value === null) {
        return false;
    }
    return page.url.startsWith(`/cook/${activeSession.value.id}`);
});

const visible = computed<boolean>(() => activeSession.value !== null && !isOnSessionPage.value);
const isPaused = computed<boolean>(() => activeSession.value?.paused_at !== null);

const now = ref<number>(Date.now());
let tick: ReturnType<typeof setInterval> | null = null;

onMounted(() => {
    tick = setInterval(() => {
        now.value = Date.now();
    }, 1000);
});

onBeforeUnmount(() => {
    if (tick !== null) {
        clearInterval(tick);
    }
});

const elapsed = computed<string>(() => {
    if (activeSession.value === null) {
        return '00:00';
    }
    const startMs = new Date(activeSession.value.started_at).getTime();
    const baseMs = isPaused.value && activeSession.value.paused_at !== null
        ? new Date(activeSession.value.paused_at).getTime()
        : now.value;
    return formatStopwatch(Math.max(0, baseMs - startMs));
});
</script>

<template>
    <Transition
        enter-active-class="transition duration-300 ease-out"
        enter-from-class="opacity-0 translate-y-4 scale-95"
        enter-to-class="opacity-100 translate-y-0 scale-100"
        leave-active-class="transition duration-200 ease-in"
        leave-from-class="opacity-100 translate-y-0 scale-100"
        leave-to-class="opacity-0 translate-y-4 scale-95"
    >
        <Link
            v-if="visible && activeSession"
            :href="showCookSession(activeSession.id)"
            :class="[
                'group fixed bottom-4 right-4 z-50 flex items-center gap-2.5 rounded-full px-3 py-2 pr-4 shadow-tile transition active:scale-[0.97]',
                isPaused
                    ? 'bg-block-sky text-ink hover:shadow-tile-hover'
                    : 'bg-brand text-ink hover:shadow-tile-hover',
            ]"
        >
            <span
                :class="[
                    'relative grid size-9 shrink-0 place-items-center rounded-full bg-ink text-cream',
                    !isPaused && 'animate-[pulse_2s_ease-in-out_infinite]',
                ]"
            >
                <Pause v-if="isPaused" class="size-4" />
                <ChefHat v-else class="size-4" />
                <span
                    v-if="!isPaused"
                    class="absolute inset-0 -z-10 animate-ping rounded-full bg-ink/40"
                />
            </span>
            <div class="flex flex-col leading-tight">
                <span class="text-[10px] font-semibold uppercase tracking-[0.2em] text-ink/65">
                    {{ isPaused ? 'Gepauzeerd' : 'Live aan het koken' }}
                </span>
                <span class="flex items-center gap-2 text-sm font-semibold">
                    <span class="line-clamp-1 max-w-[12rem]">
                        {{ activeSession.recipe_title }}
                    </span>
                    <span class="font-mono tabular-nums text-ink/80">{{ elapsed }}</span>
                </span>
            </div>
        </Link>
    </Transition>
</template>
