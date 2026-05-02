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
    Plus,
    Timer,
    X,
} from 'lucide-vue-next';
import { computed, onBeforeUnmount, onMounted, ref, watch } from 'vue';
import { Button } from '@/components/ui/button';
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
import { durationBetween, formatDuration, formatStopwatch } from '@/lib/duration';
import { groupBySection } from '@/lib/sections';
import { formatQuantity } from '@/lib/units';
import {
    complete as completeSession,
    destroy as destroySession,
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
const ingredientGroups = computed(() => groupBySection(props.session.recipe.ingredients));
const stepGroups = computed(() => groupBySection(props.session.recipe.steps));
const ingredientPct = computed(() =>
    totalIngredients.value === 0 ? 0 : (checkedIngredients.value.size / totalIngredients.value) * 100,
);
const stepPct = computed(() =>
    totalSteps.value === 0 ? 0 : (checkedSteps.value.size / totalSteps.value) * 100,
);

const now = ref<number>(Date.now());
const { remaining, timers, start: startTimer, dismiss: dismissTimer, checkForFinished } =
    useStepTimers(now);

let tick: ReturnType<typeof setInterval> | null = null;
onMounted(() => {
    if (!isCompleted.value) {
        tick = setInterval(() => {
            now.value = Date.now();
            checkForFinished();
        }, 500);
    }
});
onBeforeUnmount(() => {
    if (tick !== null) {
        clearInterval(tick);
    }
});

useWakeLock(() => !isCompleted.value);

const elapsedMs = computed(() =>
    isCompleted.value
        ? durationBetween(props.session.started_at, props.session.completed_at)
        : Math.max(0, now.value - new Date(props.session.started_at).getTime()),
);
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
</script>

<template>
    <Head :title="`Koken: ${session.recipe.title}`" />

    <div class="flex min-h-svh flex-col bg-background pb-32">
        <header
            class="sticky top-0 z-20 border-b border-sidebar-border/70 bg-background/90 backdrop-blur-md dark:border-sidebar-border"
        >
            <div class="flex items-center gap-2 px-3 py-2.5">
                <ChefHat class="size-5 shrink-0 text-muted-foreground" />
                <h1 class="line-clamp-1 flex-1 text-sm font-semibold sm:text-base">
                    {{ session.recipe.title }}
                </h1>
                <div
                    class="flex shrink-0 items-center gap-1.5 rounded-full px-2.5 py-1 text-sm font-semibold tabular-nums"
                    :class="
                        isCompleted
                            ? 'bg-muted text-muted-foreground'
                            : 'bg-primary text-primary-foreground'
                    "
                    :title="isCompleted ? `Voltooid in ${elapsedLabel}` : 'Tijd sinds start'"
                >
                    <Clock class="size-3.5" :class="{ 'animate-pulse': !isCompleted }" />
                    <span>{{ elapsedLive }}</span>
                </div>
                <DropdownMenu>
                    <DropdownMenuTrigger as-child>
                        <Button type="button" variant="ghost" size="icon" class="size-9 shrink-0">
                            <MoreVertical class="size-4" />
                        </Button>
                    </DropdownMenuTrigger>
                    <DropdownMenuContent align="end">
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
            <div class="flex items-center gap-3 px-3 pb-2">
                <span class="text-xs text-muted-foreground">Personen</span>
                <div class="ml-auto flex items-center gap-2">
                    <Button
                        type="button"
                        variant="outline"
                        size="icon"
                        class="size-9"
                        :disabled="multiplier <= 0.25"
                        @click="bumpMultiplier(-0.25)"
                    >
                        <Minus class="size-4" />
                    </Button>
                    <div class="flex min-w-[72px] flex-col items-center leading-tight">
                        <span class="text-lg font-semibold tabular-nums">{{ scaledServings }}</span>
                        <span class="text-[10px] text-muted-foreground">×{{ multiplier }}</span>
                    </div>
                    <Button
                        type="button"
                        variant="outline"
                        size="icon"
                        class="size-9"
                        @click="bumpMultiplier(0.25)"
                    >
                        <Plus class="size-4" />
                    </Button>
                </div>
            </div>
        </header>

        <main class="mx-auto flex w-full max-w-2xl flex-1 flex-col gap-6 px-3 py-4">
            <section>
                <div class="mb-2 flex items-baseline justify-between">
                    <h2 class="text-lg font-semibold">Ingrediënten</h2>
                    <span class="text-xs tabular-nums text-muted-foreground">
                        {{ checkedIngredients.size }}/{{ totalIngredients }}
                    </span>
                </div>
                <div class="mb-3 h-1 overflow-hidden rounded-full bg-muted">
                    <div
                        class="h-full bg-primary transition-all duration-200"
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
                            class="px-1 pt-1 text-xs font-semibold uppercase tracking-wide text-muted-foreground"
                        >
                            {{ group.section }}
                        </h3>
                        <ul class="flex flex-col gap-1">
                            <li
                                v-for="ingredient in group.items"
                                :key="ingredient.id"
                                class="flex items-stretch gap-0 overflow-hidden rounded-xl border border-sidebar-border/70 dark:border-sidebar-border"
                                :class="{ 'opacity-50': checkedIngredients.has(ingredient.id) }"
                            >
                                <button
                                    type="button"
                                    class="flex shrink-0 items-center justify-center px-3 transition active:bg-muted"
                                    :aria-label="
                                        checkedIngredients.has(ingredient.id)
                                            ? `${ingredient.name} unmarken`
                                            : `${ingredient.name} aanvinken`
                                    "
                                    @click="toggleIngredientCheck(ingredient.id)"
                                >
                                    <span
                                        class="flex size-7 items-center justify-center rounded-full border-2"
                                        :class="
                                            checkedIngredients.has(ingredient.id)
                                                ? 'border-primary bg-primary text-primary-foreground'
                                                : 'border-input'
                                        "
                                    >
                                        <Check v-if="checkedIngredients.has(ingredient.id)" class="size-4" />
                                    </span>
                                </button>
                                <div
                                    class="flex flex-1 items-center gap-3 py-3 pr-3 text-sm"
                                    :class="{ 'line-through': checkedIngredients.has(ingredient.id) }"
                                >
                                    <span class="flex-1">{{ ingredient.name }}</span>
                                    <span class="shrink-0 text-muted-foreground tabular-nums">
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

            <section>
                <div class="mb-2 flex items-baseline justify-between">
                    <h2 class="text-lg font-semibold">Stappen</h2>
                    <span class="text-xs tabular-nums text-muted-foreground">
                        {{ checkedSteps.size }}/{{ totalSteps }}
                    </span>
                </div>
                <div class="mb-3 h-1 overflow-hidden rounded-full bg-muted">
                    <div
                        class="h-full bg-primary transition-all duration-200"
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
                            class="px-1 pt-1 text-xs font-semibold uppercase tracking-wide text-muted-foreground"
                        >
                            {{ group.section }}
                        </h3>
                        <ol class="flex flex-col gap-2">
                            <li
                                v-for="step in group.items"
                                :key="step.id"
                                class="rounded-xl border border-sidebar-border/70 dark:border-sidebar-border"
                                :class="{ 'bg-muted/40': checkedSteps.has(step.id) }"
                            >
                                <div class="flex items-stretch gap-0">
                                    <button
                                        type="button"
                                        class="flex shrink-0 items-start justify-center px-3 pt-3 transition active:bg-muted"
                                        :aria-label="
                                            checkedSteps.has(step.id)
                                                ? `Stap ${step.position} unmarken`
                                                : `Stap ${step.position} klaar`
                                        "
                                        @click="toggleStepCheck(step.id)"
                                    >
                                        <span
                                            class="flex size-7 items-center justify-center rounded-full border-2 text-xs font-semibold"
                                            :class="
                                                checkedSteps.has(step.id)
                                                    ? 'border-primary bg-primary text-primary-foreground'
                                                    : 'border-input'
                                            "
                                        >
                                            <Check v-if="checkedSteps.has(step.id)" class="size-4" />
                                            <span v-else>{{ step.position }}</span>
                                        </span>
                                    </button>
                                    <div class="flex flex-1 flex-col gap-2 py-3 pr-3">
                                        <p
                                            class="whitespace-pre-line text-sm leading-relaxed"
                                            :class="{
                                                'text-muted-foreground line-through': checkedSteps.has(step.id),
                                            }"
                                        >
                                            {{ step.body }}
                                        </p>
                                        <div
                                            v-if="detectTimerMinutes(step.body) !== null"
                                            class="flex items-center gap-2"
                                        >
                                            <button
                                                v-if="!timers.get(step.id)"
                                                type="button"
                                                class="flex items-center gap-1.5 rounded-full border border-primary/40 bg-primary/5 px-3 py-1 text-xs font-medium text-primary transition active:scale-95"
                                                @click="
                                                    startTimer(step.id, detectTimerMinutes(step.body)!)
                                                "
                                            >
                                                <Timer class="size-3.5" />
                                                Start {{ detectTimerMinutes(step.body) }} min
                                            </button>
                                            <div
                                                v-else
                                                class="flex items-center gap-2 rounded-full px-3 py-1 text-xs font-semibold tabular-nums"
                                                :class="
                                                    timerFinished(step.id)
                                                        ? 'bg-primary text-primary-foreground'
                                                        : 'bg-primary/10 text-primary'
                                                "
                                            >
                                                <Bell
                                                    v-if="timerFinished(step.id)"
                                                    class="size-3.5 animate-pulse"
                                                />
                                                <Timer v-else class="size-3.5 animate-pulse" />
                                                <span>{{ timerLabel(step.id) }}</span>
                                                <button
                                                    type="button"
                                                    class="-mr-1 ml-1 flex size-5 items-center justify-center rounded-full hover:bg-foreground/10"
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
            class="fixed bottom-24 right-4 z-30 flex size-14 items-center justify-center rounded-full bg-foreground text-background shadow-lg transition active:scale-95"
            :aria-label="notes ? 'Notitie bewerken' : 'Notitie toevoegen'"
            @click="notesOpen = true"
        >
            <MessageSquare class="size-5" />
            <span
                v-if="notes"
                class="absolute -right-0.5 -top-0.5 flex size-3 items-center justify-center rounded-full bg-primary"
            />
        </button>

        <Sheet v-model:open="notesOpen">
            <SheetContent side="bottom" class="rounded-t-2xl">
                <SheetHeader class="text-left">
                    <SheetTitle>Opmerkingen</SheetTitle>
                    <SheetDescription>
                        Wat viel op? Wat ga je volgende keer anders doen?
                    </SheetDescription>
                </SheetHeader>
                <textarea
                    v-model="notes"
                    autofocus
                    class="mt-2 min-h-[160px] w-full rounded-xl border border-input bg-transparent px-3 py-2 text-sm shadow-xs outline-none focus-visible:border-ring focus-visible:ring-2 focus-visible:ring-ring/50"
                    placeholder="Te zout, volgende keer minder bouillon..."
                />
            </SheetContent>
        </Sheet>

        <footer
            class="sticky bottom-0 z-20 border-t border-sidebar-border/70 bg-background/90 px-3 py-3 backdrop-blur-md dark:border-sidebar-border"
        >
            <div class="mx-auto max-w-2xl">
                <Button
                    type="button"
                    size="lg"
                    class="h-12 w-full text-base"
                    :disabled="isCompleted"
                    @click="completeAndExit"
                >
                    <BellOff v-if="isCompleted" class="size-4" />
                    {{ isCompleted ? 'Voltooid' : 'Klaar met koken' }}
                </Button>
            </div>
        </footer>
    </div>
</template>
