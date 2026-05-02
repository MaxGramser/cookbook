<script setup lang="ts">
import { Form, Head, Link } from '@inertiajs/vue3';
import { Clock, Pencil, Play, Users } from 'lucide-vue-next';
import CookSessionController from '@/actions/App/Http/Controllers/CookSessionController';
import RecipeController from '@/actions/App/Http/Controllers/RecipeController';
import Heading from '@/components/Heading.vue';
import { Button } from '@/components/ui/button';
import { durationBetween, formatDuration } from '@/lib/duration';
import { groupBySection } from '@/lib/sections';
import { formatQuantity } from '@/lib/units';
import { index as recipesIndex, edit as editRecipe } from '@/routes/recipes';
import { computed } from 'vue';
import type { CookSessionSummary, Recipe } from '@/types/recipes';

const props = defineProps<{
    recipe: Recipe;
    recentSessions: CookSessionSummary[];
}>();

const ingredientGroups = computed(() => groupBySection(props.recipe.ingredients));
const stepGroups = computed(() => groupBySection(props.recipe.steps));

defineOptions({
    layout: { breadcrumbs: [{ title: 'Recepten', href: recipesIndex() }] },
});

function confirmDelete(event: Event): void {
    if (!window.confirm('Recept verwijderen?')) {
        event.preventDefault();
    }
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
</script>

<template>
    <Head :title="recipe.title" />

    <div class="flex flex-col gap-6 p-4 md:p-6">
        <div class="flex flex-wrap items-start justify-between gap-4">
            <Heading :title="recipe.title" />
            <div class="flex gap-2">
                <Button as-child variant="outline">
                    <Link :href="editRecipe(recipe.id)">
                        <Pencil class="size-4" /> Bewerken
                    </Link>
                </Button>
                <Form
                    v-bind="CookSessionController.store.form({ recipe: recipe.id })"
                    v-slot="{ processing }"
                >
                    <Button type="submit" :disabled="processing">
                        <Play class="size-4" /> Start koken
                    </Button>
                </Form>
            </div>
        </div>

        <div class="grid gap-6 md:grid-cols-[2fr_3fr]">
            <div class="overflow-hidden rounded-xl border border-sidebar-border/70 dark:border-sidebar-border">
                <div class="aspect-video w-full bg-muted">
                    <img
                        v-if="recipe.image_path"
                        :src="`/storage/${recipe.image_path}`"
                        :alt="recipe.title"
                        class="h-full w-full object-cover"
                    />
                </div>
                <div class="flex flex-wrap gap-4 p-4 text-sm text-muted-foreground">
                    <span v-if="recipe.cook_time_minutes" class="flex items-center gap-1">
                        <Clock class="size-4" /> {{ recipe.cook_time_minutes }} min
                    </span>
                    <span class="flex items-center gap-1">
                        <Users class="size-4" /> {{ recipe.servings }} personen
                    </span>
                    <a
                        v-if="recipe.source_url"
                        :href="recipe.source_url"
                        target="_blank"
                        rel="noopener"
                        class="underline underline-offset-2"
                    >
                        bron
                    </a>
                </div>
            </div>

            <div class="flex flex-col gap-6">
                <section>
                    <h2 class="mb-3 font-semibold">Ingrediënten</h2>
                    <div class="flex flex-col gap-4">
                        <div
                            v-for="(group, groupIdx) in ingredientGroups"
                            :key="`ig-${groupIdx}`"
                            class="flex flex-col gap-1"
                        >
                            <h3
                                v-if="group.section"
                                class="text-xs font-semibold uppercase tracking-wide text-muted-foreground"
                            >
                                {{ group.section }}
                            </h3>
                            <ul class="divide-y rounded-xl border border-sidebar-border/70 dark:border-sidebar-border">
                                <li
                                    v-for="ingredient in group.items"
                                    :key="ingredient.id"
                                    class="flex justify-between gap-4 px-4 py-2 text-sm"
                                >
                                    <span>{{ ingredient.name }}</span>
                                    <span class="text-muted-foreground">
                                        {{ formatQuantity(ingredient.quantity, ingredient.unit) || ingredient.raw_text }}
                                    </span>
                                </li>
                            </ul>
                        </div>
                    </div>
                </section>

                <section>
                    <h2 class="mb-3 font-semibold">Stappen</h2>
                    <div class="flex flex-col gap-4">
                        <div
                            v-for="(group, groupIdx) in stepGroups"
                            :key="`sg-${groupIdx}`"
                            class="flex flex-col gap-2"
                        >
                            <h3
                                v-if="group.section"
                                class="text-xs font-semibold uppercase tracking-wide text-muted-foreground"
                            >
                                {{ group.section }}
                            </h3>
                            <ol class="flex flex-col gap-2">
                                <li
                                    v-for="step in group.items"
                                    :key="step.id"
                                    class="flex gap-3 rounded-xl border border-sidebar-border/70 p-3 text-sm dark:border-sidebar-border"
                                >
                                    <span class="size-7 shrink-0 rounded-full bg-muted text-center leading-7 font-medium">
                                        {{ step.position }}
                                    </span>
                                    <p class="whitespace-pre-line">{{ step.body }}</p>
                                </li>
                            </ol>
                        </div>
                    </div>
                </section>

                <section v-if="recipe.notes">
                    <h2 class="mb-2 font-semibold">Notities</h2>
                    <p class="whitespace-pre-line text-sm text-muted-foreground">{{ recipe.notes }}</p>
                </section>

                <section v-if="recentSessions.length > 0">
                    <h2 class="mb-2 font-semibold">Laatste keer gekookt</h2>
                    <ul class="text-sm text-muted-foreground">
                        <li
                            v-for="session in recentSessions"
                            :key="session.id"
                            class="flex justify-between gap-3 border-t py-2 first:border-t-0"
                        >
                            <span class="flex-1">{{ formatDate(session.completed_at ?? session.started_at) }}</span>
                            <span v-if="sessionDuration(session)" class="tabular-nums">
                                {{ sessionDuration(session) }}
                            </span>
                            <span class="tabular-nums">×{{ session.servings_multiplier }}</span>
                        </li>
                    </ul>
                </section>

                <Form
                    v-bind="RecipeController.destroy.form(recipe.id)"
                    v-slot="{ processing }"
                    @submit="confirmDelete"
                >
                    <Button type="submit" variant="ghost" class="text-destructive" :disabled="processing">
                        Recept verwijderen
                    </Button>
                </Form>
            </div>
        </div>
    </div>
</template>
