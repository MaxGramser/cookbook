<script setup lang="ts">
import { Form, Head, Link, router } from '@inertiajs/vue3';
import { ChefHat, Clock, ExternalLink, Pencil, Play, Printer, ShoppingBasket, Star, Trash2, Users } from 'lucide-vue-next';
import { computed, ref } from 'vue';
import CookSessionController from '@/actions/App/Http/Controllers/CookSessionController';
import GrocerySessionController from '@/actions/App/Http/Controllers/GrocerySessionController';
import RecipeController from '@/actions/App/Http/Controllers/RecipeController';
import PrintRecipeDialog from '@/components/PrintRecipeDialog.vue';
import { durationBetween, formatDuration } from '@/lib/duration';
import { groupBySection } from '@/lib/sections';
import { formatQuantity } from '@/lib/units';
import { index as recipesIndex, edit as editRecipe } from '@/routes/recipes';
import type { CookSessionSummary, Recipe, Tag, TagColor } from '@/types/recipes';

const props = defineProps<{
    recipe: Recipe;
    recentSessions: CookSessionSummary[];
}>();

const ingredientGroups = computed(() => groupBySection(props.recipe.ingredients));
const stepGroups = computed(() => groupBySection(props.recipe.steps));
const printOpen = ref<boolean>(false);

defineOptions({
    layout: { breadcrumbs: [{ title: 'Recepten', href: recipesIndex() }] },
});

function confirmDelete(event: Event): void {
    if (!window.confirm('Recept verwijderen?')) {
        event.preventDefault();
    }
}

function toggleStar(): void {
    router.post(
        RecipeController.toggleStar.url(props.recipe.id),
        {},
        { preserveScroll: true, preserveState: false },
    );
}

function sessionDuration(session: CookSessionSummary): string | null {
    if (!session.started_at || !session.completed_at) {
        return null;
    }

    return formatDuration(durationBetween(session.started_at, session.completed_at));
}

function formatDate(value: string | null | undefined): string {
    if (!value) {
        return '';
    }

    return new Intl.DateTimeFormat('nl-NL', {
        dateStyle: 'medium',
        timeStyle: 'short',
    }).format(new Date(value));
}

const tagColorClass: Record<TagColor, string> = {
    cream: 'bg-cream text-ink border-rule',
    lime: 'bg-block-lime text-ink border-transparent',
    pink: 'bg-block-pink text-ink border-transparent',
    sky: 'bg-block-sky text-ink border-transparent',
    accent: 'bg-brand text-ink border-transparent',
    ink: 'bg-ink text-cream border-transparent',
};

function tagFilterUrl(tag: Tag): string {
    const params = new URLSearchParams();
    params.set('tags', String(tag.id));
    return `${recipesIndex().url}?${params.toString()}`;
}
</script>

<template>
    <Head :title="recipe.title" />

    <div class="flex flex-col gap-5 p-4 md:p-6">
        <div class="grid gap-5 md:grid-cols-[5fr_4fr]">
            <div class="overflow-hidden rounded-3xl bg-cream-soft shadow-tile">
                <div class="aspect-[4/3] w-full overflow-hidden bg-ink/5">
                    <img
                        v-if="recipe.image_path"
                        :src="`/storage/${recipe.image_path}`"
                        :alt="recipe.title"
                        class="h-full w-full object-cover"
                    />
                    <div
                        v-else
                        class="flex h-full w-full items-center justify-center text-ink-faint"
                    >
                        <ChefHat class="size-12" />
                    </div>
                </div>
                <div class="flex flex-wrap items-center gap-2 p-5">
                    <span
                        v-if="recipe.cook_time_minutes"
                        class="flex items-center gap-1.5 rounded-full bg-ink px-3 py-1 text-xs font-semibold tabular-nums text-cream"
                    >
                        <Clock class="size-3.5" /> {{ recipe.cook_time_minutes }} min
                    </span>
                    <span
                        class="flex items-center gap-1.5 rounded-full bg-block-lime px-3 py-1 text-xs font-semibold tabular-nums text-ink"
                    >
                        <Users class="size-3.5" /> {{ recipe.servings }} pers
                    </span>
                    <a
                        v-if="recipe.source_url"
                        :href="recipe.source_url"
                        target="_blank"
                        rel="noopener"
                        class="flex items-center gap-1.5 rounded-full border border-rule px-3 py-1 text-xs text-ink-soft transition hover:bg-ink/5"
                    >
                        <ExternalLink class="size-3.5" /> bron
                    </a>
                    <Link
                        v-for="tag in recipe.tags ?? []"
                        :key="tag.id"
                        :href="tagFilterUrl(tag)"
                        :class="[
                            'rounded-full border px-3 py-1 text-xs font-semibold transition active:scale-[0.97] hover:-translate-y-px',
                            tagColorClass[tag.color] ?? tagColorClass.cream,
                        ]"
                    >
                        {{ tag.name }}
                    </Link>
                </div>
            </div>

            <div class="flex flex-col justify-between gap-5 rounded-3xl bg-ink p-6 text-cream md:p-8">
                <div>
                    <p class="text-[11px] font-semibold uppercase tracking-[0.22em] text-cream/55">
                        Recept
                    </p>
                    <h1 class="mt-2 font-display text-4xl leading-[1.05] tracking-tight md:text-5xl">
                        {{ recipe.title }}
                    </h1>
                </div>
                <div class="flex flex-wrap gap-2">
                    <Form
                        v-bind="CookSessionController.store.form({ recipe: recipe.id })"
                        v-slot="{ processing }"
                    >
                        <button
                            type="submit"
                            :disabled="processing"
                            class="inline-flex items-center gap-2 rounded-full bg-brand px-5 py-2.5 text-sm font-medium text-ink transition active:scale-[0.98] hover:bg-[#d35a31] disabled:opacity-50"
                        >
                            <Play class="size-4" /> Start koken
                        </button>
                    </Form>
                    <Form
                        v-bind="GrocerySessionController.store.form({ recipe: recipe.id })"
                        v-slot="{ processing }"
                    >
                        <button
                            type="submit"
                            :disabled="processing"
                            class="inline-flex items-center gap-2 rounded-full bg-block-lime px-5 py-2.5 text-sm font-medium text-ink transition active:scale-[0.98] hover:bg-[#b5d23f] disabled:opacity-50"
                        >
                            <ShoppingBasket class="size-4" /> Boodschappen
                        </button>
                    </Form>
                    <Link
                        :href="editRecipe(recipe.id)"
                        class="inline-flex items-center gap-2 rounded-full border border-cream/25 px-5 py-2.5 text-sm font-medium transition hover:bg-cream/10"
                    >
                        <Pencil class="size-4" /> Bewerken
                    </Link>
                    <button
                        type="button"
                        :class="[
                            'inline-flex items-center gap-2 rounded-full px-5 py-2.5 text-sm font-medium transition',
                            recipe.is_starred
                                ? 'bg-brand text-ink hover:bg-[#d35a31]'
                                : 'border border-cream/25 hover:bg-cream/10',
                        ]"
                        @click="toggleStar"
                    >
                        <Star class="size-4" :fill="recipe.is_starred ? 'currentColor' : 'none'" />
                        {{ recipe.is_starred ? 'Favoriet' : 'Markeer als favoriet' }}
                    </button>
                    <button
                        type="button"
                        class="inline-flex items-center gap-2 rounded-full border border-cream/25 px-5 py-2.5 text-sm font-medium transition hover:bg-cream/10"
                        @click="printOpen = true"
                    >
                        <Printer class="size-4" /> Print
                    </button>
                </div>
            </div>
        </div>

        <div class="grid gap-5 md:grid-cols-[3fr_5fr]">
            <section class="rounded-3xl bg-cream-soft p-5 md:p-6">
                <div class="mb-4 flex items-baseline justify-between">
                    <h2 class="font-display text-2xl leading-tight">Ingrediënten</h2>
                    <span class="text-[11px] font-semibold uppercase tracking-[0.18em] text-ink-faint">
                        {{ recipe.ingredients.length }} regels
                    </span>
                </div>
                <div class="flex flex-col gap-4">
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
                                class="flex items-center justify-between gap-3 rounded-xl border border-rule bg-cream px-4 py-2.5 text-sm"
                            >
                                <span class="flex-1">{{ ingredient.name }}</span>
                                <span class="shrink-0 font-semibold tabular-nums text-ink-soft">
                                    {{ formatQuantity(ingredient.quantity, ingredient.unit) || ingredient.raw_text }}
                                </span>
                            </li>
                        </ul>
                    </div>
                </div>
            </section>

            <section class="rounded-3xl bg-cream-soft p-5 md:p-6">
                <div class="mb-4 flex items-baseline justify-between">
                    <h2 class="font-display text-2xl leading-tight">Stappen</h2>
                    <span class="text-[11px] font-semibold uppercase tracking-[0.18em] text-ink-faint">
                        {{ recipe.steps.length }} stappen
                    </span>
                </div>
                <div class="flex flex-col gap-4">
                    <div
                        v-for="(group, groupIdx) in stepGroups"
                        :key="`sg-${groupIdx}`"
                        class="flex flex-col gap-2"
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
                                class="flex gap-3 rounded-xl border border-rule bg-cream p-4 text-sm"
                            >
                                <span
                                    class="grid size-8 shrink-0 place-items-center rounded-full bg-ink text-sm font-semibold text-cream"
                                >
                                    {{ step.position }}
                                </span>
                                <p class="flex-1 whitespace-pre-line leading-relaxed">{{ step.body }}</p>
                            </li>
                        </ol>
                    </div>
                </div>
            </section>
        </div>

        <section v-if="recipe.notes" class="rounded-3xl bg-block-pink p-5 md:p-6">
            <h2 class="font-display text-2xl leading-tight">Notities</h2>
            <p class="mt-2 whitespace-pre-line text-sm leading-relaxed text-ink/85">
                {{ recipe.notes }}
            </p>
        </section>

        <section v-if="recentSessions.length > 0" class="rounded-3xl bg-cream-soft p-5 md:p-6">
            <h2 class="mb-3 font-display text-2xl leading-tight">Laatst gekookt</h2>
            <ul class="flex flex-col gap-2">
                <li
                    v-for="session in recentSessions"
                    :key="session.id"
                    class="flex items-center justify-between gap-3 rounded-xl border border-rule bg-cream px-4 py-3 text-sm"
                >
                    <span class="flex-1">
                        {{ formatDate(session.completed_at ?? session.started_at) }}
                    </span>
                    <span v-if="sessionDuration(session)" class="tabular-nums text-ink-soft">
                        {{ sessionDuration(session) }}
                    </span>
                    <span
                        class="rounded-full bg-block-lime px-2.5 py-0.5 text-xs font-semibold tabular-nums"
                    >
                        ×{{ session.servings_multiplier }}
                    </span>
                </li>
            </ul>
        </section>

        <Form
            v-bind="RecipeController.destroy.form(recipe.id)"
            v-slot="{ processing }"
            @submit="confirmDelete"
        >
            <button
                type="submit"
                :disabled="processing"
                class="inline-flex items-center gap-2 rounded-full border border-warn/30 px-5 py-2.5 text-sm font-medium text-warn transition hover:bg-warn/5 disabled:opacity-50"
            >
                <Trash2 class="size-4" /> Recept verwijderen
            </button>
        </Form>

        <PrintRecipeDialog v-model:open="printOpen" :recipe="recipe" />
    </div>
</template>
