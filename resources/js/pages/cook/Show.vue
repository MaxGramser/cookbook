<script setup lang="ts">
import { Head, router } from '@inertiajs/vue3';
import { Check, ChefHat, Clock, Minus, Plus } from 'lucide-vue-next';
import { computed, onBeforeUnmount, onMounted, ref, watch } from 'vue';
import { Button } from '@/components/ui/button';
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

const baseServings = props.session.recipe.servings;
const scaledServings = computed(() => Math.round(baseServings * multiplier.value));
const totalIngredients = computed(() => props.session.recipe.ingredients.length);
const totalSteps = computed(() => props.session.recipe.steps.length);
const isCompleted = computed(() => props.session.completed_at !== null);
const ingredientGroups = computed(() => groupBySection(props.session.recipe.ingredients));
const stepGroups = computed(() => groupBySection(props.session.recipe.steps));

const now = ref<number>(Date.now());
let tick: ReturnType<typeof setInterval> | null = null;

onMounted(() => {
    if (!isCompleted.value) {
        tick = setInterval(() => {
            now.value = Date.now();
        }, 1000);
    }
});
onBeforeUnmount(() => {
    if (tick !== null) {
        clearInterval(tick);
    }
});

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
    if (!confirm('Sessie afbreken?')) {
        return;
    }
    router.delete(destroySession(props.session.id).url);
}
</script>

<template>
    <Head :title="`Koken: ${session.recipe.title}`" />

    <div class="flex min-h-svh flex-col bg-background pb-24">
        <header
            class="sticky top-0 z-20 border-b border-sidebar-border/70 bg-background/80 backdrop-blur dark:border-sidebar-border"
        >
            <div class="flex items-center gap-3 px-4 py-3">
                <ChefHat class="size-5 text-muted-foreground" />
                <h1 class="line-clamp-1 flex-1 font-semibold">{{ session.recipe.title }}</h1>
                <div
                    class="flex shrink-0 items-center gap-1.5 rounded-full px-3 py-1 text-base font-semibold tabular-nums"
                    :class="
                        isCompleted
                            ? 'bg-muted text-muted-foreground'
                            : 'bg-primary text-primary-foreground'
                    "
                    :title="isCompleted ? `Voltooid in ${elapsedLabel}` : 'Tijd sinds start'"
                >
                    <Clock class="size-4" :class="{ 'animate-pulse': !isCompleted }" />
                    <span>{{ elapsedLive }}</span>
                </div>
            </div>
            <div class="flex items-center gap-2 px-4 pb-3">
                <span class="text-sm text-muted-foreground">Personen</span>
                <div class="ml-auto flex items-center gap-2">
                    <Button
                        type="button"
                        variant="outline"
                        size="icon"
                        class="size-10"
                        :disabled="multiplier <= 0.25"
                        @click="bumpMultiplier(-0.25)"
                    >
                        <Minus class="size-4" />
                    </Button>
                    <div class="flex min-w-[88px] flex-col items-center">
                        <span class="text-xl font-semibold tabular-nums">{{ scaledServings }}</span>
                        <span class="text-xs text-muted-foreground">×{{ multiplier }}</span>
                    </div>
                    <Button
                        type="button"
                        variant="outline"
                        size="icon"
                        class="size-10"
                        @click="bumpMultiplier(0.25)"
                    >
                        <Plus class="size-4" />
                    </Button>
                </div>
            </div>
        </header>

        <main class="mx-auto flex w-full max-w-2xl flex-1 flex-col gap-6 px-4 py-4">
            <section>
                <div class="mb-2 flex items-baseline justify-between">
                    <h2 class="text-lg font-semibold">Ingrediënten</h2>
                    <span class="text-xs text-muted-foreground">
                        {{ checkedIngredients.size }}/{{ totalIngredients }}
                    </span>
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
                            <li v-for="ingredient in group.items" :key="ingredient.id">
                                <button
                                    type="button"
                                    class="flex w-full items-center gap-3 rounded-xl border border-sidebar-border/70 px-3 py-3 text-left transition active:scale-[0.99] dark:border-sidebar-border"
                                    :class="{ 'opacity-50 line-through': checkedIngredients.has(ingredient.id) }"
                                    @click="toggleIngredientCheck(ingredient.id)"
                                >
                                    <span
                                        class="flex size-7 shrink-0 items-center justify-center rounded-full border"
                                        :class="
                                            checkedIngredients.has(ingredient.id)
                                                ? 'border-primary bg-primary text-primary-foreground'
                                                : 'border-input'
                                        "
                                    >
                                        <Check v-if="checkedIngredients.has(ingredient.id)" class="size-4" />
                                    </span>
                                    <span class="flex-1 text-sm">{{ ingredient.name }}</span>
                                    <span class="shrink-0 text-sm text-muted-foreground tabular-nums">
                                        {{
                                            formatQuantity(ingredient.quantity, ingredient.unit, multiplier) ||
                                            ingredient.raw_text
                                        }}
                                    </span>
                                </button>
                            </li>
                        </ul>
                    </div>
                </div>
            </section>

            <section>
                <div class="mb-2 flex items-baseline justify-between">
                    <h2 class="text-lg font-semibold">Stappen</h2>
                    <span class="text-xs text-muted-foreground">
                        {{ checkedSteps.size }}/{{ totalSteps }}
                    </span>
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
                            <li v-for="step in group.items" :key="step.id">
                                <button
                                    type="button"
                                    class="flex w-full items-start gap-3 rounded-xl border border-sidebar-border/70 px-3 py-3 text-left transition active:scale-[0.99] dark:border-sidebar-border"
                                    :class="{ 'opacity-60': checkedSteps.has(step.id) }"
                                    @click="toggleStepCheck(step.id)"
                                >
                                    <span
                                        class="flex size-7 shrink-0 items-center justify-center rounded-full border text-sm font-medium"
                                        :class="
                                            checkedSteps.has(step.id)
                                                ? 'border-primary bg-primary text-primary-foreground'
                                                : 'border-input'
                                        "
                                    >
                                        <Check v-if="checkedSteps.has(step.id)" class="size-4" />
                                        <span v-else>{{ step.position }}</span>
                                    </span>
                                    <p
                                        class="flex-1 whitespace-pre-line text-sm"
                                        :class="{ 'line-through': checkedSteps.has(step.id) }"
                                    >
                                        {{ step.body }}
                                    </p>
                                </button>
                            </li>
                        </ol>
                    </div>
                </div>
            </section>

            <section class="flex flex-col gap-2">
                <label for="notes" class="text-sm font-semibold">Opmerkingen</label>
                <textarea
                    id="notes"
                    v-model="notes"
                    class="min-h-[100px] rounded-xl border border-input bg-transparent px-3 py-2 text-sm shadow-xs outline-none focus-visible:border-ring focus-visible:ring-2 focus-visible:ring-ring/50"
                    placeholder="Wat viel op? Volgende keer anders doen..."
                />
            </section>
        </main>

        <footer
            class="sticky bottom-0 z-20 border-t border-sidebar-border/70 bg-background/80 px-4 py-3 backdrop-blur dark:border-sidebar-border"
        >
            <div class="mx-auto flex max-w-2xl gap-2">
                <Button type="button" variant="ghost" class="flex-1" @click="cancel">
                    Stoppen
                </Button>
                <Button type="button" class="flex-1" :disabled="isCompleted" @click="completeAndExit">
                    {{ isCompleted ? 'Voltooid' : 'Klaar met koken' }}
                </Button>
            </div>
        </footer>
    </div>
</template>
