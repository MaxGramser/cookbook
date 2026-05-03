<script setup lang="ts">
import { Head, router } from '@inertiajs/vue3';
import {
    Bell,
    BellOff,
    Check,
    ChefHat,
    Clock,
    MessageSquare,
    MoreVertical,
    Minus,
    Pause,
    Play,
    Plus,
    Timer,
    X,
} from 'lucide-vue-next';
import { computed, onBeforeUnmount, onMounted, ref, watch } from 'vue';
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
import { useStepTimers, detectTimerMinutes } from '@/composables/useStepTimers';
import { useWakeLock } from '@/composables/useWakeLock';
import { formatDuration, formatStopwatch } from '@/lib/duration';
import { groupBySection } from '@/lib/sections';
import { formatQuantity } from '@/lib/units';
import {
    complete as completeSession,
    destroy as destroySession,
    pause as pauseSession,
    resume as resumeSession,
    update as updateSession,
} from '@/routes/cook';
import { toggle as toggleIngredient } from '@/routes/cook/ingredient';
import { toggle as toggleStep } from '@/routes/cook/step';
import { index as recipesIndex } from '@/routes/recipes';
import type { CookSessionDetail } from '@/types/recipes';

const props = defineProps<{ session: CookSessionDetail }>();

defineOptions({
    layout: { breadcrumbs: [{ title: 'Recepten', href: recipesIndex() }] },
});

const checkedIngredients = ref<Set<number>>(new Set(props.session.checked_ingredient_ids));
const checkedSteps = ref<Set<number>>(new Set(props.session.checked_step_ids));
const multiplier = ref<number>(Number(props.session.servings_multiplier) || 1);
const notes = ref<string>(props.session.notes ?? '');
const notesOpen = ref<boolean>(false);

const baseServings = props.session.recipe.servings;
const scaledServings = computed(() => Math.round(baseServings * multiplier.value));
const totalIngredients = computed(() => props.session.recipe.ingredients.length);
const totalSteps = computed(() => props.session.recipe.steps.length);
const isCompleted = computed(() => props.session.completed_at !== null);
const isPaused = computed(() => props.session.paused_at !== null && !isCompleted.value);
const ingredientGroups = computed(() => groupBySection(props.session.recipe.ingredients));
const stepGroups = computed(() => groupBySection(props.session.recipe.steps));
const ingredientPct = computed(() =>
    totalIngredients.value === 0 ? 0 : (checkedIngredients.value.size / totalIngredients.value) * 100,
);
const stepPct = computed(() =>
    totalSteps.value === 0 ? 0 : (checkedSteps.value.size / totalSteps.value) * 100,
);

const now = ref<number>(Date.now());
const {
    remaining,
    timers,
    start: startTimer,
    dismiss: dismissTimer,
    pauseAll: pauseStepTimers,
    resumeAll: resumeStepTimers,
    checkForFinished,
} = useStepTimers(now);

let tick: ReturnType<typeof setInterval> | null = null;
onMounted(() => {
    if (!isCompleted.value && !isPaused.value) {
        tick = setInterval(() => {
            now.value = Date.now();
            checkForFinished();
        }, 500);
    }
});

watch(isPaused, (paused) => {
    if (paused) {
        pauseStepTimers();
        if (tick !== null) {
            clearInterval(tick);
            tick = null;
        }
    } else if (!isCompleted.value) {
        resumeStepTimers();
        if (tick === null) {
            tick = setInterval(() => {
                now.value = Date.now();
                checkForFinished();
            }, 500);
        }
    }
});
onBeforeUnmount(() => {
    if (tick !== null) {
        clearInterval(tick);
    }
});

useWakeLock(() => !isCompleted.value);

const elapsedMs = computed(() => {
    const start = new Date(props.session.started_at).getTime();
    const pausedMs = (props.session.paused_seconds ?? 0) * 1000;
    if (isCompleted.value && props.session.completed_at) {
        return Math.max(0, new Date(props.session.completed_at).getTime() - start - pausedMs);
    }
    if (isPaused.value && props.session.paused_at) {
        return Math.max(0, new Date(props.session.paused_at).getTime() - start - pausedMs);
    }
    return Math.max(0, now.value - start - pausedMs);
});
const elapsedLive = computed(() => formatStopwatch(elapsedMs.value));
const elapsedLabel = computed(() => formatDuration(elapsedMs.value));

function bumpMultiplier(delta: number): void {
    const stepped = Math.round((multiplier.value + delta) * 4) / 4;
    multiplier.value = Math.max(0.25, Math.min(20, stepped));
    persistMultiplier();
}

function persistMultiplier(): void {
    router.patch(
        updateSession(props.session.id).url,
        { servings_multiplier: multiplier.value },
        { preserveScroll: true, only: [] },
    );
}

let notesTimer: ReturnType<typeof setTimeout> | null = null;
watch(notes, (next) => {
    if (notesTimer !== null) {
        clearTimeout(notesTimer);
    }
    notesTimer = setTimeout(() => {
        router.patch(
            updateSession(props.session.id).url,
            { notes: next },
            { preserveScroll: true, only: [] },
        );
    }, 600);
});

function toggleIngredientCheck(id: number): void {
    const checked = !checkedIngredients.value.has(id);
    if (checked) {
        checkedIngredients.value.add(id);
    } else {
        checkedIngredients.value.delete(id);
    }
    router.post(
        toggleIngredient([props.session.id, id]).url,
        { checked },
        { preserveScroll: true, only: [] },
    );
}

function toggleStepCheck(id: number): void {
    const checked = !checkedSteps.value.has(id);
    if (checked) {
        checkedSteps.value.add(id);
    } else {
        checkedSteps.value.delete(id);
    }
    router.post(
        toggleStep([props.session.id, id]).url,
        { checked },
        { preserveScroll: true, only: [] },
    );
}

function completeAndExit(): void {
    router.post(completeSession(props.session.id).url, {}, { preserveScroll: false });
}

function cancel(): void {
    if (!confirm('Sessie afbreken? Alle voortgang gaat verloren.')) {
        return;
    }
    router.delete(destroySession(props.session.id).url);
}

function togglePause(): void {
    const url = isPaused.value
        ? resumeSession(props.session.id).url
        : pauseSession(props.session.id).url;
    router.post(url, {}, { preserveScroll: true });
}

function timerLabel(stepId: number): string | null {
    const ms = remaining.value.get(stepId);
    if (ms === undefined) {
        return null;
    }
    return formatStopwatch(ms);
}

function timerFinished(stepId: number): boolean {
    return timers.value.get(stepId)?.finished ?? false;
}

/** Prefer the explicit timer the user set in the editor; otherwise sniff the body. */
function stepTimerMinutes(step: { timer_minutes: number | null; body: string }): number | null {
    return step.timer_minutes ?? detectTimerMinutes(step.body);
}
</script>

<template>
    <Head :title="`Koken: ${session.recipe.title}`" />

    <div class="flex min-h-svh flex-col bg-cream pb-32 text-ink">
        <header class="sticky top-0 z-20 bg-cream/90 backdrop-blur-md">
            <div class="mx-auto max-w-2xl px-3 pt-3">
                <div class="flex items-center gap-2 rounded-full border border-rule bg-cream-soft px-3 py-2 shadow-tile">
                    <span class="grid size-8 shrink-0 place-items-center rounded-full bg-ink text-cream">
                        <ChefHat class="size-4" />
                    </span>
                    <h1 class="line-clamp-1 flex-1 text-sm font-semibold">
                        {{ session.recipe.title }}
                    </h1>
                    <DropdownMenu>
                        <DropdownMenuTrigger as-child>
                            <button
                                type="button"
                                class="grid size-8 shrink-0 place-items-center rounded-full text-ink-soft transition hover:bg-ink/5"
                            >
                                <MoreVertical class="size-4" />
                            </button>
                        </DropdownMenuTrigger>
                        <DropdownMenuContent align="end">
                            <DropdownMenuItem v-if="!isCompleted" @select="togglePause">
                                <Pause v-if="!isPaused" class="size-4" />
                                <Play v-else class="size-4" />
                                {{ isPaused ? 'Hervat' : 'Pauzeer' }}
                            </DropdownMenuItem>
                            <DropdownMenuItem @select="notesOpen = true">
                                <MessageSquare class="size-4" /> Notitie toevoegen
                            </DropdownMenuItem>
                            <DropdownMenuSeparator />
                            <DropdownMenuItem class="text-destructive" @select="cancel">
                                <X class="size-4" /> Sessie afbreken
                            </DropdownMenuItem>
                        </DropdownMenuContent>
                    </DropdownMenu>
                </div>
            </div>
        </header>

        <main class="mx-auto flex w-full max-w-2xl flex-1 flex-col gap-3 px-3 pt-4">
            <section
                :class="[
                    'rounded-3xl px-6 py-7 shadow-tile transition',
                    isCompleted
                        ? 'bg-block-lime text-ink'
                        : isPaused
                          ? 'bg-block-sky text-ink'
                          : 'bg-brand text-ink',
                ]"
            >
                <div class="flex items-start justify-between">
                    <div>
                        <p class="text-[11px] font-semibold uppercase tracking-[0.22em] text-ink/65">
                            {{ isCompleted ? 'Voltooid in' : isPaused ? 'Gepauzeerd' : 'Bezig met koken' }}
                        </p>
                        <p class="mt-2 font-display text-6xl leading-none tabular-nums tracking-tight">
                            {{ elapsedLive }}
                        </p>
                        <p
                            v-if="isCompleted"
                            class="mt-2 text-xs text-ink/65 tabular-nums"
                        >
                            {{ elapsedLabel }}
                        </p>
                    </div>
                    <button
                        v-if="!isCompleted"
                        type="button"
                        class="grid size-14 shrink-0 place-items-center rounded-full bg-ink text-cream shadow-tile transition active:scale-95"
                        :title="isPaused ? 'Hervat' : 'Pauzeer'"
                        :aria-label="isPaused ? 'Hervat' : 'Pauzeer'"
                        @click="togglePause"
                    >
                        <Pause v-if="!isPaused" class="size-5 animate-pulse" />
                        <Play v-else class="size-5" />
                    </button>
                    <span
                        v-else
                        class="grid size-14 shrink-0 place-items-center rounded-full bg-ink text-cream"
                    >
                        <Check class="size-6" />
                    </span>
                </div>

                <div class="mt-6 flex items-center justify-between gap-3 rounded-2xl bg-ink/10 px-3 py-2">
                    <div>
                        <p class="text-[10px] font-semibold uppercase tracking-[0.2em] text-ink/65">
                            Personen
                        </p>
                        <p class="font-display text-2xl tabular-nums leading-tight">
                            {{ scaledServings }}
                            <span class="text-xs text-ink/55">×{{ multiplier }}</span>
                        </p>
                    </div>
                    <div class="flex items-center gap-2">
                        <button
                            type="button"
                            class="grid size-10 place-items-center rounded-full bg-cream text-ink shadow-tile transition active:scale-95 disabled:opacity-40"
                            :disabled="multiplier <= 0.25"
                            @click="bumpMultiplier(-0.25)"
                        >
                            <Minus class="size-4" />
                        </button>
                        <button
                            type="button"
                            class="grid size-10 place-items-center rounded-full bg-cream text-ink shadow-tile transition active:scale-95"
                            @click="bumpMultiplier(0.25)"
                        >
                            <Plus class="size-4" />
                        </button>
                    </div>
                </div>
            </section>

            <section class="rounded-3xl bg-cream-soft p-5 md:p-6">
                <div class="mb-3 flex items-baseline justify-between">
                    <h2 class="font-display text-2xl leading-tight">Ingrediënten</h2>
                    <span class="text-[11px] font-semibold uppercase tracking-[0.18em] tabular-nums text-ink-faint">
                        {{ checkedIngredients.size }}/{{ totalIngredients }}
                    </span>
                </div>
                <div class="mb-4 h-1.5 overflow-hidden rounded-full bg-ink/10">
                    <div
                        class="h-full rounded-full bg-brand transition-all duration-300"
                        :style="{ width: `${ingredientPct}%` }"
                    />
                </div>
                <div class="flex flex-col gap-3">
                    <div
                        v-for="(group, groupIdx) in ingredientGroups"
                        :key="`ig-${groupIdx}`"
                        class="flex flex-col gap-1"
                    >
                        <h3
                            v-if="group.section"
                            class="px-1 pt-1 text-[11px] font-semibold uppercase tracking-[0.2em] text-ink-faint"
                        >
                            {{ group.section }}
                        </h3>
                        <ul class="flex flex-col gap-1">
                            <li
                                v-for="ingredient in group.items"
                                :key="ingredient.id"
                                :class="[
                                    'flex items-stretch gap-0 overflow-hidden rounded-xl bg-cream transition',
                                    checkedIngredients.has(ingredient.id) && 'opacity-50',
                                ]"
                            >
                                <button
                                    type="button"
                                    class="flex shrink-0 items-center justify-center px-3 transition active:bg-ink/5"
                                    :aria-label="
                                        checkedIngredients.has(ingredient.id)
                                            ? `${ingredient.name} unmarken`
                                            : `${ingredient.name} aanvinken`
                                    "
                                    @click="toggleIngredientCheck(ingredient.id)"
                                >
                                    <span
                                        :class="[
                                            'flex size-7 items-center justify-center rounded-full border-2 transition',
                                            checkedIngredients.has(ingredient.id)
                                                ? 'border-ink bg-ink text-cream'
                                                : 'border-ink/25',
                                        ]"
                                    >
                                        <Check v-if="checkedIngredients.has(ingredient.id)" class="size-4" />
                                    </span>
                                </button>
                                <div
                                    :class="[
                                        'flex flex-1 items-center gap-3 py-3 pr-3 text-sm',
                                        checkedIngredients.has(ingredient.id) && 'line-through',
                                    ]"
                                >
                                    <span class="flex-1">{{ ingredient.name }}</span>
                                    <span class="shrink-0 font-semibold tabular-nums text-ink-soft">
                                        {{
                                            formatQuantity(ingredient.quantity, ingredient.unit, multiplier) ||
                                            ingredient.raw_text
                                        }}
                                    </span>
                                </div>
                            </li>
                        </ul>
                    </div>
                </div>
            </section>

            <section class="rounded-3xl bg-cream-soft p-5 md:p-6">
                <div class="mb-3 flex items-baseline justify-between">
                    <h2 class="font-display text-2xl leading-tight">Stappen</h2>
                    <span class="text-[11px] font-semibold uppercase tracking-[0.18em] tabular-nums text-ink-faint">
                        {{ checkedSteps.size }}/{{ totalSteps }}
                    </span>
                </div>
                <div class="mb-4 h-1.5 overflow-hidden rounded-full bg-ink/10">
                    <div
                        class="h-full rounded-full bg-brand transition-all duration-300"
                        :style="{ width: `${stepPct}%` }"
                    />
                </div>
                <div class="flex flex-col gap-3">
                    <div
                        v-for="(group, groupIdx) in stepGroups"
                        :key="`sg-${groupIdx}`"
                        class="flex flex-col gap-1"
                    >
                        <h3
                            v-if="group.section"
                            class="px-1 pt-1 text-[11px] font-semibold uppercase tracking-[0.2em] text-ink-faint"
                        >
                            {{ group.section }}
                        </h3>
                        <ol class="flex flex-col gap-2">
                            <li
                                v-for="step in group.items"
                                :key="step.id"
                                :class="[
                                    'rounded-2xl bg-cream transition',
                                    checkedSteps.has(step.id) && 'opacity-60',
                                ]"
                            >
                                <div class="flex items-stretch gap-0">
                                    <button
                                        type="button"
                                        class="flex shrink-0 items-start justify-center px-3 pt-3 transition active:bg-ink/5"
                                        :aria-label="
                                            checkedSteps.has(step.id)
                                                ? `Stap ${step.position} unmarken`
                                                : `Stap ${step.position} klaar`
                                        "
                                        @click="toggleStepCheck(step.id)"
                                    >
                                        <span
                                            :class="[
                                                'flex size-8 items-center justify-center rounded-full text-sm font-semibold tabular-nums transition',
                                                checkedSteps.has(step.id)
                                                    ? 'bg-ink text-cream'
                                                    : 'bg-block-lime text-ink',
                                            ]"
                                        >
                                            <Check v-if="checkedSteps.has(step.id)" class="size-4" />
                                            <span v-else>{{ step.position }}</span>
                                        </span>
                                    </button>
                                    <div class="flex flex-1 flex-col gap-2 py-3 pr-3">
                                        <p
                                            :class="[
                                                'whitespace-pre-line text-sm leading-relaxed',
                                                checkedSteps.has(step.id) && 'line-through text-ink-soft',
                                            ]"
                                        >
                                            {{ step.body }}
                                        </p>
                                        <div
                                            v-if="stepTimerMinutes(step) !== null"
                                            class="flex items-center gap-2"
                                        >
                                            <button
                                                v-if="!timers.get(step.id)"
                                                type="button"
                                                class="flex items-center gap-1.5 rounded-full bg-ink px-3 py-1.5 text-xs font-semibold text-cream transition active:scale-95 hover:bg-[#3a2c24]"
                                                @click="
                                                    startTimer(step.id, stepTimerMinutes(step)!)
                                                "
                                            >
                                                <Timer class="size-3.5" />
                                                Start {{ stepTimerMinutes(step) }} min
                                            </button>
                                            <div
                                                v-else
                                                :class="[
                                                    'flex items-center gap-2 rounded-full px-3 py-1.5 text-xs font-semibold tabular-nums shadow-tile',
                                                    timerFinished(step.id)
                                                        ? 'bg-block-lime text-ink'
                                                        : 'bg-brand text-ink',
                                                ]"
                                            >
                                                <Bell
                                                    v-if="timerFinished(step.id)"
                                                    class="size-3.5 animate-pulse"
                                                />
                                                <Timer v-else class="size-3.5 animate-pulse" />
                                                <span>{{ timerLabel(step.id) }}</span>
                                                <button
                                                    type="button"
                                                    class="-mr-1 ml-1 grid size-5 place-items-center rounded-full hover:bg-ink/10"
                                                    :aria-label="
                                                        timerFinished(step.id)
                                                            ? 'Sluiten'
                                                            : 'Timer annuleren'
                                                    "
                                                    @click="dismissTimer(step.id)"
                                                >
                                                    <X class="size-3.5" />
                                                </button>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </li>
                        </ol>
                    </div>
                </div>
            </section>
        </main>

        <button
            type="button"
            class="fixed bottom-24 right-4 z-30 grid size-14 place-items-center rounded-full bg-ink text-cream shadow-tile transition active:scale-95 hover:bg-[#3a2c24]"
            :aria-label="notes ? 'Notitie bewerken' : 'Notitie toevoegen'"
            @click="notesOpen = true"
        >
            <MessageSquare class="size-5" />
            <span
                v-if="notes"
                class="absolute -right-0.5 -top-0.5 size-3 rounded-full bg-brand"
            />
        </button>

        <Sheet v-model:open="notesOpen">
            <SheetContent side="bottom" class="rounded-t-3xl border-rule bg-cream-soft">
                <SheetHeader class="text-left">
                    <SheetTitle class="font-display text-2xl">Opmerkingen</SheetTitle>
                    <SheetDescription>
                        Wat viel op? Wat ga je volgende keer anders doen?
                    </SheetDescription>
                </SheetHeader>
                <textarea
                    v-model="notes"
                    autofocus
                    class="mt-3 min-h-[160px] w-full rounded-xl border border-rule bg-cream px-4 py-3 text-sm leading-relaxed outline-none transition placeholder:text-ink-faint focus:border-brand focus:ring-2 focus:ring-brand/30"
                    placeholder="Te zout, volgende keer minder bouillon..."
                />
            </SheetContent>
        </Sheet>

        <footer class="sticky bottom-0 z-20 bg-cream/90 px-3 py-3 backdrop-blur-md">
            <div class="mx-auto max-w-2xl">
                <button
                    type="button"
                    class="inline-flex h-14 w-full items-center justify-center gap-2 rounded-full bg-brand text-base font-semibold text-ink shadow-tile transition active:scale-[0.99] hover:bg-[#d35a31] disabled:opacity-50"
                    :disabled="isCompleted"
                    @click="completeAndExit"
                >
                    <Clock v-if="!isCompleted" class="size-5" />
                    <BellOff v-else class="size-5" />
                    {{ isCompleted ? 'Voltooid' : 'Klaar met koken' }}
                </button>
            </div>
        </footer>
    </div>
</template>
