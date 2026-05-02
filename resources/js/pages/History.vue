<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import { Clock } from 'lucide-vue-next';
import { computed } from 'vue';
import Heading from '@/components/Heading.vue';
import { durationBetween, formatDuration } from '@/lib/duration';
import { dashboard } from '@/routes';
import { show as showRecipe } from '@/routes/recipes';
import type { CookSessionSummary } from '@/types/recipes';

const props = defineProps<{ sessions: CookSessionSummary[] }>();

defineOptions({
    layout: { breadcrumbs: [{ title: 'Geschiedenis', href: dashboard() }] },
});

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

    <div class="flex flex-col gap-6 p-4 md:p-6">
        <Heading title="Wat heb ik gekookt" description="Alle voltooide koksessies" />

        <div v-if="sessions.length === 0" class="rounded-xl border border-dashed p-12 text-center">
            <p class="text-muted-foreground">Nog geen voltooide sessies.</p>
        </div>

        <div v-for="(items, label) in grouped" :key="label" class="flex flex-col gap-2">
            <h2 class="text-sm font-semibold uppercase tracking-wide text-muted-foreground">
                {{ label }}
            </h2>
            <ul class="divide-y rounded-xl border border-sidebar-border/70 dark:border-sidebar-border">
                <li v-for="session in items" :key="session.id">
                    <Link
                        v-if="session.recipe"
                        :href="showRecipe(session.recipe.id)"
                        class="flex items-center gap-3 px-4 py-3 transition hover:bg-muted/50"
                    >
                        <div class="size-12 shrink-0 overflow-hidden rounded-lg bg-muted">
                            <img
                                v-if="session.recipe.image_path"
                                :src="`/storage/${session.recipe.image_path}`"
                                :alt="session.recipe.title"
                                class="h-full w-full object-cover"
                            />
                        </div>
                        <div class="min-w-0 flex-1">
                            <p class="line-clamp-1 font-medium">{{ session.recipe.title }}</p>
                            <p class="flex items-center gap-2 text-xs text-muted-foreground">
                                <span>{{ formatDate(session.completed_at!) }}</span>
                                <span>·</span>
                                <span>×{{ session.servings_multiplier }}</span>
                                <span v-if="sessionDuration(session)" class="flex items-center gap-1">
                                    · <Clock class="size-3" /> {{ sessionDuration(session) }}
                                </span>
                            </p>
                        </div>
                    </Link>
                </li>
            </ul>
        </div>
    </div>
</template>
