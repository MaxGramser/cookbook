<script setup lang="ts">
import { Head, useForm } from '@inertiajs/vue3';
import { Plus, X } from 'lucide-vue-next';
import { ref } from 'vue';
import Heading from '@/components/Heading.vue';
import InputError from '@/components/InputError.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import {
    compileIngredients,
    compileSteps,
    expandIngredientsToRows,
    expandStepsToRows,
    type IngredientRow,
    type StepRow,
} from '@/lib/recipeForm';
import { index as recipesIndex, show as showRecipe, update as updateRecipe } from '@/routes/recipes';
import type { Recipe, Unit } from '@/types/recipes';

const props = defineProps<{ recipe: Recipe }>();

defineOptions({
    layout: {
        breadcrumbs: [{ title: 'Recepten', href: recipesIndex() }],
    },
});

function unitToText(unit: Unit | string | null): string {
    if (!unit) {
        return '';
    }
    return unit === 'tsp' ? 'tl' : unit === 'tbsp' ? 'el' : unit === 'piece' ? 'stuks' : unit;
}

const ingredientRows = ref<IngredientRow[]>(
    expandIngredientsToRows(props.recipe.ingredients, unitToText),
);
const stepRows = ref<StepRow[]>(expandStepsToRows(props.recipe.steps));

const form = useForm({
    _method: 'put',
    title: props.recipe.title,
    source_url: props.recipe.source_url ?? '',
    servings: props.recipe.servings,
    cook_time_minutes: props.recipe.cook_time_minutes,
    notes: props.recipe.notes ?? '',
    image: null as File | null,
    ingredients: compileIngredients(ingredientRows.value),
    steps: compileSteps(stepRows.value),
});

function addIngredientItem(): void {
    ingredientRows.value.push({ kind: 'item', quantity_text: '', unit_text: '', name: '' });
}

function addIngredientHeader(): void {
    ingredientRows.value.push({ kind: 'header', name: '' });
    ingredientRows.value.push({ kind: 'item', quantity_text: '', unit_text: '', name: '' });
}

function removeIngredientRow(index: number): void {
    ingredientRows.value.splice(index, 1);
    if (ingredientRows.value.length === 0) {
        addIngredientItem();
    }
}

function addStepItem(): void {
    stepRows.value.push({ kind: 'item', body: '' });
}

function addStepHeader(): void {
    stepRows.value.push({ kind: 'header', name: '' });
    stepRows.value.push({ kind: 'item', body: '' });
}

function removeStepRow(index: number): void {
    stepRows.value.splice(index, 1);
    if (stepRows.value.length === 0) {
        addStepItem();
    }
}

function stepNumber(index: number): number {
    let n = 0;
    for (let i = 0; i <= index; i++) {
        if (stepRows.value[i]?.kind === 'item') {
            n++;
        }
    }
    return n;
}

function submit(): void {
    form.ingredients = compileIngredients(ingredientRows.value);
    form.steps = compileSteps(stepRows.value);
    form.post(updateRecipe(props.recipe.id).url, { forceFormData: true });
}

function onImage(event: Event): void {
    const target = event.target as HTMLInputElement;
    form.image = target.files?.[0] ?? null;
}
</script>

<template>
    <Head :title="`${recipe.title} bewerken`" />

    <form class="flex flex-col gap-6 p-4 md:p-6" @submit.prevent="submit">
        <Heading :title="`${recipe.title} bewerken`" />

        <div class="grid gap-4 md:grid-cols-2">
            <div class="grid gap-2 md:col-span-2">
                <Label for="title">Titel</Label>
                <Input id="title" v-model="form.title" required />
                <InputError :message="form.errors.title" />
            </div>

            <div class="grid gap-2">
                <Label for="servings">Personen</Label>
                <Input id="servings" v-model.number="form.servings" type="number" min="1" max="99" required />
                <InputError :message="form.errors.servings" />
            </div>

            <div class="grid gap-2">
                <Label for="cook_time">Kooktijd (min)</Label>
                <Input
                    id="cook_time"
                    :model-value="form.cook_time_minutes ?? ''"
                    type="number"
                    min="0"
                    @update:model-value="
                        (value) => (form.cook_time_minutes = value === '' ? null : Number(value))
                    "
                />
                <InputError :message="form.errors.cook_time_minutes" />
            </div>

            <div class="grid gap-2 md:col-span-2">
                <Label for="source_url">Bron URL (optioneel)</Label>
                <Input id="source_url" v-model="form.source_url" type="url" />
            </div>

            <div class="grid gap-2 md:col-span-2">
                <Label for="image">Vervang afbeelding</Label>
                <Input id="image" type="file" accept="image/*" @change="onImage" />
            </div>
        </div>

        <section class="flex flex-col gap-3">
            <div class="flex items-center justify-between">
                <h2 class="font-semibold">Ingrediënten</h2>
                <div class="flex gap-2">
                    <Button type="button" variant="outline" size="sm" @click="addIngredientHeader">
                        <Plus class="size-4" /> Kopje
                    </Button>
                    <Button type="button" variant="outline" size="sm" @click="addIngredientItem">
                        <Plus class="size-4" /> Regel
                    </Button>
                </div>
            </div>

            <template v-for="(row, idx) in ingredientRows" :key="`ing-${idx}`">
                <div v-if="row.kind === 'header'" class="grid grid-cols-[1fr_auto] items-center gap-2">
                    <Input
                        v-model="row.name"
                        placeholder="Kopje, bv 'Voor de saus'"
                        class="font-semibold"
                    />
                    <Button type="button" variant="ghost" size="icon" @click="removeIngredientRow(idx)">
                        <X class="size-4" />
                    </Button>
                </div>
                <div v-else class="grid grid-cols-[1fr_auto] items-start gap-2">
                    <div class="grid grid-cols-[80px_90px_1fr] gap-2 sm:grid-cols-[100px_120px_1fr]">
                        <Input v-model="row.quantity_text" placeholder="200" />
                        <Input v-model="row.unit_text" placeholder="g / el" />
                        <Input v-model="row.name" />
                    </div>
                    <Button type="button" variant="ghost" size="icon" @click="removeIngredientRow(idx)">
                        <X class="size-4" />
                    </Button>
                </div>
            </template>
        </section>

        <section class="flex flex-col gap-3">
            <div class="flex items-center justify-between">
                <h2 class="font-semibold">Stappen</h2>
                <div class="flex gap-2">
                    <Button type="button" variant="outline" size="sm" @click="addStepHeader">
                        <Plus class="size-4" /> Kopje
                    </Button>
                    <Button type="button" variant="outline" size="sm" @click="addStepItem">
                        <Plus class="size-4" /> Stap
                    </Button>
                </div>
            </div>

            <template v-for="(row, idx) in stepRows" :key="`step-${idx}`">
                <div v-if="row.kind === 'header'" class="grid grid-cols-[1fr_auto] items-center gap-2">
                    <Input
                        v-model="row.name"
                        placeholder="Kopje, bv 'Voorbereiden'"
                        class="font-semibold"
                    />
                    <Button type="button" variant="ghost" size="icon" @click="removeStepRow(idx)">
                        <X class="size-4" />
                    </Button>
                </div>
                <div v-else class="flex items-start gap-2">
                    <span class="mt-3 size-7 shrink-0 rounded-full bg-muted text-center leading-7 text-sm font-medium">
                        {{ stepNumber(idx) }}
                    </span>
                    <textarea
                        v-model="row.body"
                        class="min-h-[72px] flex-1 rounded-md border border-input bg-transparent px-3 py-2 text-sm shadow-xs outline-none focus-visible:border-ring focus-visible:ring-2 focus-visible:ring-ring/50"
                    />
                    <Button type="button" variant="ghost" size="icon" @click="removeStepRow(idx)">
                        <X class="size-4" />
                    </Button>
                </div>
            </template>
        </section>

        <section class="grid gap-2">
            <Label for="notes">Notities</Label>
            <textarea
                id="notes"
                v-model="form.notes"
                class="min-h-[88px] rounded-md border border-input bg-transparent px-3 py-2 text-sm shadow-xs outline-none focus-visible:border-ring focus-visible:ring-2 focus-visible:ring-ring/50"
            />
        </section>

        <div class="flex justify-end gap-2">
            <Button as-child variant="ghost">
                <a :href="showRecipe(recipe.id).url">Annuleer</a>
            </Button>
            <Button type="submit" :disabled="form.processing">Opslaan</Button>
        </div>
    </form>
</template>
