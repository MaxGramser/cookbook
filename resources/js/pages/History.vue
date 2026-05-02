<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import { ChefHat, Clock } from 'lucide-vue-next';
import { computed } from 'vue';
import { durationBetween, formatDuration } from '@/lib/duration';
import { dashboard } from '@/routes';
import { show as showRecipe } from '@/routes/recipes';
import type { CookSessionSummary } from '@/types/recipes';

const props = defineProps<{ sessions: CookSessionSummary[] }>();

defineOptions({
    layout: { breadcrumbs: [{ title: 'Geschiedenis', href: dashboard() }] },
});

const totalCompleted = computed(
    () => props.sessions.filter((s) => s.completed_at).length,
);

const grouped = computed(() => {
    const out: Record<string, CookSessionSummary[]> = {};
    for (const session of props.sessions) {
        if (!session.completed_at) {
            continue;
        }
        const key = new Intl.DateTimeFormat('nl-NL', {
            year: 'numeric',
            month: 'long',
        }).format(new Date(session.completed_at));
        out[key] ??= [];
        out[key].push(session);
    }
    return out;
});

const monthPalette = ['lime', 'pink', 'sky', 'cream'] as const;
type MonthBlock = (typeof monthPalette)[number];

function monthBlock(idx: number): MonthBlock {
    return monthPalette[idx % monthPalette.length];
}

const monthBgClass: Record<MonthBlock, string> = {
    lime: 'bg-block-lime',
    pink: 'bg-block-pink',
    sky: 'bg-block-sky',
    cream: 'bg-cream-soft',
};

function formatDate(value: string): string {
    return new Intl.DateTimeFormat('nl-NL', {
        weekday: 'short',
        day: 'numeric',
        month: 'short',
    }).format(new Date(value));
}

function sessionDuration(session: CookSessionSummary): string | null {
    if (!session.started_at || !session.completed_at) {
        return null;
    }
    return formatDuration(durationBetween(session.started_at, session.completed_at));
}
</script>

<template>
    <Head title="Geschiedenis" />

    <div class="flex flex-col gap-5 p-4 md:gap-6 md:p-6">
        <div class="rounded-3xl bg-ink p-6 text-cream md:p-8">
            <div class="flex flex-wrap items-end justify-between gap-4">
                <div>
                    <p class="text-[11px] font-semibold uppercase tracking-[0.22em] text-cream/55">
                        Geschiedenis
                    </p>
                    <h1 class="mt-1 font-display text-4xl leading-[1.05] tracking-tight md:text-5xl">
                        Wat heb ik
                        <span class="italic text-brand">gekookt</span>
                    </h1>
                </div>
                <div class="grid grid-cols-2 gap-3">
                    <div class="rounded-2xl bg-block-lime px-4 py-3 text-ink">
                        <p class="text-[10px] font-semibold uppercase tracking-[0.2em] text-ink/60">
                            Sessies
                        </p>
                        <p class="font-display text-3xl tabular-nums">
                            {{ totalCompleted }}
                        </p>
                    </div>
                    <div class="rounded-2xl bg-brand px-4 py-3 text-ink">
                        <p class="text-[10px] font-semibold uppercase tracking-[0.2em] text-ink/65">
                            Maanden
                        </p>
                        <p class="font-display text-3xl tabular-nums">
                            {{ Object.keys(grouped).length }}
                        </p>
                    </div>
                </div>
            </div>
        </div>

        <div
            v-if="totalCompleted === 0"
            class="rounded-3xl border border-dashed border-rule bg-cream-soft p-12 text-center"
        >
            <div class="mx-auto mb-4 flex size-12 items-center justify-center rounded-full bg-ink text-cream">
                <ChefHat class="size-5" />
            </div>
            <h2 class="font-display text-2xl">Nog niets gekookt.</h2>
            <p class="mt-2 text-sm text-ink-soft">
                Start een sessie vanaf een recept om hier verschijning op te bouwen.
            </p>
        </div>

        <div
            v-for="(items, label, idx) in grouped"
            :key="label"
            :class="['relative overflow-hidden rounded-3xl p-5 md:p-6', monthBgClass[monthBlock(Number(idx))]]"
        >
            <div class="mb-4 flex items-baseline justify-between gap-3">
                <h2 class="font-display text-3xl leading-tight capitalize">
                    {{ label }}
                </h2>
                <span class="rounded-full bg-ink px-3 py-1 text-xs font-semibold text-cream tabular-nums">
                    {{ items.length }}×
                </span>
            </div>

            <ul class="flex flex-col gap-2">
                <li v-for="session in items" :key="session.id">
                    <Link
                        v-if="session.recipe"
                        :href="showRecipe(session.recipe.id)"
                        class="group flex items-center gap-4 rounded-2xl bg-cream px-3 py-3 transition hover:-translate-y-0.5 hover:shadow-tile"
                    >
                        <div class="size-14 shrink-0 overflow-hidden rounded-xl bg-ink/5">
                            <img
                                v-if="session.recipe.image_path"
                                :src="`/storage/${session.recipe.image_path}`"
                                :alt="session.recipe.title"
                                class="h-full w-full object-cover transition duration-500 group-hover:scale-105"
                            />
                            <div
                                v-else
                                class="flex h-full w-full items-center justify-center text-ink-faint"
                            >
                                <ChefHat class="size-5" />
                            </div>
                        </div>
                        <div class="min-w-0 flex-1">
                            <p class="line-clamp-1 font-display text-lg leading-tight">
                                {{ session.recipe.title }}
                            </p>
                            <div class="mt-1 flex flex-wrap items-center gap-2 text-[11px] text-ink-soft">
                                <span class="font-semibold uppercase tracking-[0.16em]">
                                    {{ formatDate(session.completed_at!) }}
                                </span>
                                <span
                                    class="rounded-full bg-ink/5 px-2 py-0.5 font-semibold tabular-nums"
                                >
                                    ×{{ session.servings_multiplier }}
                                </span>
                                <span
                                    v-if="sessionDuration(session)"
                                    class="flex items-center gap-1 rounded-full bg-ink/5 px-2 py-0.5 font-semibold tabular-nums"
                                >
                                    <Clock class="size-3" /> {{ sessionDuration(session) }}
                                </span>
                            </div>
                        </div>
                        <span
                            class="grid size-9 shrink-0 place-items-center rounded-full bg-ink text-cream transition group-hover:rotate-12"
                        >
                            <Clock class="size-4" />
                        </span>
                    </Link>
                </li>
            </ul>
        </div>
    </div>
</template>
