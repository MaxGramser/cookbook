<script setup lang="ts">
import { Head, InfiniteScroll, Link, router } from '@inertiajs/vue3';
import { ChefHat, Clock, Loader2, MessageSquare, MoreVertical, Pencil, ShoppingBasket, Trash2 } from 'lucide-vue-next';
import { computed, ref } from 'vue';
import {
    DropdownMenu,
    DropdownMenuContent,
    DropdownMenuItem,
    DropdownMenuSeparator,
    DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu';
import {
    Sheet,
    SheetContent,
    SheetDescription,
    SheetHeader,
    SheetTitle,
} from '@/components/ui/sheet';
import { durationBetween, formatDuration } from '@/lib/duration';
import { dashboard } from '@/routes';
import { destroy as destroySession, update as updateSession } from '@/routes/cook';
import { show as showRecipe } from '@/routes/recipes';
import type { CookSessionSummary, GrocerySessionSummary, Paginated } from '@/types/recipes';

const props = defineProps<{
    sessions: Paginated<CookSessionSummary>;
    grocerySessions: Paginated<GrocerySessionSummary>;
}>();

defineOptions({
    layout: { breadcrumbs: [{ title: 'Geschiedenis', href: dashboard() }] },
});

const totalCompleted = computed(
    () => props.sessions.total ?? props.sessions.data.filter((s) => s.completed_at).length,
);

const totalGroceries = computed(
    () => props.grocerySessions.total ?? props.grocerySessions.data.filter((s) => s.completed_at).length,
);

function monthKey(value: string): string {
    return new Intl.DateTimeFormat('nl-NL', {
        year: 'numeric',
        month: 'long',
    }).format(new Date(value));
}

const months = computed(() => {
    const map = new Map<
        string,
        { label: string; cook: CookSessionSummary[]; grocery: GrocerySessionSummary[] }
    >();

    for (const session of props.sessions.data) {
        if (!session.completed_at) {
            continue;
        }

        const key = monthKey(session.completed_at);

        if (!map.has(key)) {
            map.set(key, { label: key, cook: [], grocery: [] });
        }

        map.get(key)!.cook.push(session);
    }

    for (const session of props.grocerySessions.data) {
        if (!session.completed_at) {
            continue;
        }

        const key = monthKey(session.completed_at);

        if (!map.has(key)) {
            map.set(key, { label: key, cook: [], grocery: [] });
        }

        map.get(key)!.grocery.push(session);
    }

    return Array.from(map.values());
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

function formatShortDate(value: string): string {
    return new Intl.DateTimeFormat('nl-NL', {
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

const editing = ref<CookSessionSummary | null>(null);
const noteDraft = ref<string>('');
const saving = ref<boolean>(false);

function openEditNote(session: CookSessionSummary): void {
    editing.value = session;
    noteDraft.value = session.notes ?? '';
}

function closeEditNote(): void {
    editing.value = null;
    noteDraft.value = '';
    saving.value = false;
}

function saveNote(): void {
    if (editing.value === null) {
        return;
    }

    saving.value = true;
    const sessionId = editing.value.id;
    router.patch(
        updateSession(sessionId).url,
        { notes: noteDraft.value },
        {
            preserveScroll: true,
            onSuccess: () => closeEditNote(),
            onError: () => {
                saving.value = false;
            },
        },
    );
}

function deleteSession(session: CookSessionSummary): void {
    if (!confirm(`Verwijder deze sessie van ${session.recipe?.title ?? 'dit recept'}? Dit kan niet ongedaan worden gemaakt.`)) {
        return;
    }

    router.delete(destroySession(session.id).url, { preserveScroll: true });
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
                <div class="grid grid-cols-3 gap-3">
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
                            {{ months.length }}
                        </p>
                    </div>
                    <div class="rounded-2xl bg-block-sky px-4 py-3 text-ink">
                        <p class="text-[10px] font-semibold uppercase tracking-[0.2em] text-ink/65">
                            Boodschappen
                        </p>
                        <p class="font-display text-3xl tabular-nums">
                            {{ totalGroceries }}
                        </p>
                    </div>
                </div>
            </div>
        </div>

        <div
            v-if="sessions.data.length === 0 && grocerySessions.data.length === 0"
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

        <InfiniteScroll
            v-else
            data="sessions"
            items-element="#history-months"
        >
            <template #default>
                <div id="history-months" class="flex flex-col gap-5 md:gap-6">
                    <div
                        v-for="(month, idx) in months"
                        :key="month.label"
                        :data-month="month.label"
                        :class="['relative overflow-hidden rounded-3xl p-5 md:p-6', monthBgClass[monthBlock(idx)]]"
                    >
            <div class="mb-5 flex items-baseline justify-between gap-3">
                <h2 class="font-display text-3xl leading-tight capitalize md:text-4xl">
                    {{ month.label }}
                </h2>
                <span class="rounded-full bg-ink px-3 py-1 text-xs font-semibold text-cream tabular-nums">
                    {{ month.cook.length }}×
                </span>
            </div>

            <ul v-if="month.cook.length > 0" class="flex flex-col gap-3">
                <li
                    v-for="session in month.cook"
                    :key="`c-${session.id}`"
                    class="group relative flex items-stretch gap-1 overflow-hidden rounded-2xl bg-cream transition hover:-translate-y-0.5 hover:shadow-tile"
                >
                    <Link
                        v-if="session.recipe"
                        :href="showRecipe(session.recipe.id)"
                        class="flex min-w-0 flex-1 items-center gap-4 px-4 py-4 md:gap-5 md:px-5 md:py-5"
                    >
                        <div class="size-20 shrink-0 overflow-hidden rounded-2xl bg-ink/5 md:size-24">
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
                                <ChefHat class="size-6" />
                            </div>
                        </div>
                        <div class="min-w-0 flex-1">
                            <p class="line-clamp-1 font-display text-2xl leading-tight md:text-3xl">
                                {{ session.recipe.title }}
                            </p>
                            <div class="mt-2 flex flex-wrap items-center gap-2 text-xs text-ink-soft">
                                <span class="font-semibold uppercase tracking-[0.16em]">
                                    {{ formatDate(session.completed_at!) }}
                                </span>
                                <span
                                    class="rounded-full bg-ink/5 px-2.5 py-0.5 font-semibold tabular-nums"
                                >
                                    ×{{ session.servings_multiplier }}
                                </span>
                                <span
                                    v-if="sessionDuration(session)"
                                    class="flex items-center gap-1 rounded-full bg-ink/5 px-2.5 py-0.5 font-semibold tabular-nums"
                                >
                                    <Clock class="size-3" /> {{ sessionDuration(session) }}
                                </span>
                                <span
                                    v-if="session.notes"
                                    class="flex items-center gap-1 rounded-full bg-brand/15 px-2.5 py-0.5 font-semibold text-ink"
                                >
                                    <MessageSquare class="size-3" /> notitie
                                </span>
                            </div>
                            <p
                                v-if="session.notes"
                                class="mt-2 line-clamp-2 text-sm leading-snug text-ink-soft"
                            >
                                {{ session.notes }}
                            </p>
                        </div>
                    </Link>

                    <div class="flex items-center pr-2">
                        <DropdownMenu>
                            <DropdownMenuTrigger as-child>
                                <button
                                    type="button"
                                    class="grid size-9 place-items-center rounded-full text-ink-soft transition hover:bg-ink/5"
                                    :aria-label="`Acties voor ${session.recipe?.title ?? 'sessie'}`"
                                >
                                    <MoreVertical class="size-4" />
                                </button>
                            </DropdownMenuTrigger>
                            <DropdownMenuContent align="end">
                                <DropdownMenuItem @select="openEditNote(session)">
                                    <Pencil class="size-4" />
                                    {{ session.notes ? 'Notitie bewerken' : 'Notitie toevoegen' }}
                                </DropdownMenuItem>
                                <DropdownMenuSeparator />
                                <DropdownMenuItem
                                    class="text-destructive"
                                    @select="deleteSession(session)"
                                >
                                    <Trash2 class="size-4" /> Verwijderen
                                </DropdownMenuItem>
                            </DropdownMenuContent>
                        </DropdownMenu>
                    </div>
                </li>
            </ul>

            <div v-if="month.grocery.length > 0" class="mt-4">
                <p class="mb-2 flex items-center gap-1.5 px-1 text-[10px] font-semibold uppercase tracking-[0.22em] text-ink/55">
                    <ShoppingBasket class="size-3" /> Boodschappen ({{ month.grocery.length }})
                </p>
                <ul class="flex flex-wrap gap-1.5">
                    <li
                        v-for="g in month.grocery"
                        :key="`g-${g.id}`"
                        class="inline-flex items-center gap-1.5 rounded-full bg-cream/80 px-2.5 py-1 text-[11px] text-ink-soft"
                    >
                        <ShoppingBasket class="size-3 shrink-0" />
                        <Link
                            v-if="g.recipe"
                            :href="showRecipe(g.recipe.id)"
                            class="line-clamp-1 max-w-[10rem] hover:text-ink"
                        >
                            {{ g.recipe.title }}
                        </Link>
                        <span v-else class="line-clamp-1">Recept verwijderd</span>
                        <span class="font-mono tabular-nums text-ink-faint">
                            {{ formatShortDate(g.completed_at!) }}
                        </span>
                    </li>
                </ul>
            </div>
        </div>
                </div>
            </template>

            <template #loading>
                <div class="flex justify-center py-8 text-ink-faint">
                    <Loader2 class="size-5 animate-spin" />
                </div>
            </template>
        </InfiniteScroll>
    </div>

    <Sheet :open="editing !== null" @update:open="(v) => { if (!v) closeEditNote(); }">
        <SheetContent side="bottom" class="rounded-t-3xl border-rule bg-cream-soft">
            <SheetHeader class="text-left">
                <SheetTitle class="font-display text-2xl">
                    {{ editing?.recipe?.title ?? 'Notitie' }}
                </SheetTitle>
                <SheetDescription>
                    Wat viel op? Wat ga je volgende keer anders doen?
                </SheetDescription>
            </SheetHeader>
            <textarea
                v-model="noteDraft"
                autofocus
                class="mt-3 min-h-[160px] w-full rounded-xl border border-rule bg-cream px-4 py-3 text-sm leading-relaxed outline-none transition placeholder:text-ink-faint focus:border-brand focus:ring-2 focus:ring-brand/30"
                placeholder="Te zout, volgende keer minder bouillon..."
            />
            <div class="mt-4 flex items-center justify-end gap-2">
                <button
                    type="button"
                    class="rounded-full border border-rule px-5 py-2.5 text-sm font-semibold text-ink-soft transition hover:bg-ink/5"
                    @click="closeEditNote"
                >
                    Annuleren
                </button>
                <button
                    type="button"
                    class="rounded-full bg-brand px-5 py-2.5 text-sm font-semibold text-ink shadow-tile transition active:scale-[0.98] hover:bg-[#d35a31] disabled:opacity-50"
                    :disabled="saving"
                    @click="saveNote"
                >
                    {{ saving ? 'Opslaan…' : 'Opslaan' }}
                </button>
            </div>
        </SheetContent>
    </Sheet>
</template>
