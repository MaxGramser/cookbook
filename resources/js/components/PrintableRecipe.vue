<script setup lang="ts">
import { computed } from 'vue';
import { groupBySection } from '@/lib/sections';
import { formatQuantity } from '@/lib/units';
import type { Recipe } from '@/types/recipes';

const props = defineProps<{
    recipe: Recipe;
    multiplier: number;
}>();

const ingredientGroups = computed(() => groupBySection(props.recipe.ingredients));
const stepGroups = computed(() => groupBySection(props.recipe.steps));
const scaledServings = computed(() => Math.round(props.recipe.servings * props.multiplier));
</script>

<template>
    <article class="print-area font-serif text-black">
        <header class="border-b-2 border-black pb-3">
            <h1 class="text-3xl font-bold leading-tight">{{ recipe.title }}</h1>
            <p class="mt-1 text-sm">
                <span>{{ scaledServings }} personen</span>
                <span v-if="recipe.cook_time_minutes"> · {{ recipe.cook_time_minutes }} minuten</span>
                <span v-if="multiplier !== 1"> · ×{{ multiplier }} schaling</span>
                <span v-if="recipe.source_url"> · {{ recipe.source_url }}</span>
            </p>
        </header>

        <div class="print-grid mt-4">
            <section class="print-ingredients">
                <h2 class="text-base font-bold uppercase tracking-wider">Ingrediënten</h2>
                <div
                    v-for="(group, idx) in ingredientGroups"
                    :key="`ig-${idx}`"
                    class="mt-2"
                >
                    <h3 v-if="group.section" class="mt-3 text-sm font-bold italic">
                        {{ group.section }}
                    </h3>
                    <ul class="mt-1">
                        <li
                            v-for="ingredient in group.items"
                            :key="ingredient.id"
                            class="flex justify-between gap-3 border-b border-dotted border-black/30 py-1 text-sm"
                        >
                            <span>{{ ingredient.name }}</span>
                            <span class="shrink-0 font-medium tabular-nums">
                                {{
                                    formatQuantity(ingredient.quantity, ingredient.unit, multiplier) ||
                                    ingredient.raw_text
                                }}
                            </span>
                        </li>
                    </ul>
                </div>
            </section>

            <section class="print-steps">
                <h2 class="text-base font-bold uppercase tracking-wider">Bereiding</h2>
                <div
                    v-for="(group, idx) in stepGroups"
                    :key="`sg-${idx}`"
                    class="mt-2"
                >
                    <h3 v-if="group.section" class="mt-3 text-sm font-bold italic">
                        {{ group.section }}
                    </h3>
                    <ol class="mt-1 flex flex-col gap-2">
                        <li
                            v-for="step in group.items"
                            :key="step.id"
                            class="flex gap-3 text-sm leading-snug"
                        >
                            <span class="shrink-0 font-bold tabular-nums">{{ step.position }}.</span>
                            <p class="whitespace-pre-line">{{ step.body }}</p>
                        </li>
                    </ol>
                </div>
            </section>
        </div>

        <footer v-if="recipe.notes" class="mt-6 border-t border-black pt-3">
            <h2 class="text-sm font-bold uppercase tracking-wider">Notities</h2>
            <p class="mt-1 whitespace-pre-line text-sm">{{ recipe.notes }}</p>
        </footer>
    </article>
</template>

<style scoped>
.print-grid {
    display: grid;
    grid-template-columns: 1fr 2fr;
    gap: 1.5rem;
}
@media (max-width: 600px) {
    .print-grid {
        grid-template-columns: 1fr;
    }
}
</style>
