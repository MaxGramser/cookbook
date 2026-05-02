<script setup lang="ts">
import { Head, Link, useForm } from '@inertiajs/vue3';
import { ImagePlus, Plus, X } from 'lucide-vue-next';
import { ref } from 'vue';
import InputError from '@/components/InputError.vue';
import {
    compileIngredients,
    compileSteps,
    type IngredientRow,
    type StepRow,
} from '@/lib/recipeForm';
import { dashboard } from '@/routes';
import { index as recipesIndex, store as storeRecipe } from '@/routes/recipes';

defineOptions({
    layout: {
        breadcrumbs: [
            { title: 'Recepten', href: recipesIndex() },
            { title: 'Nieuw', href: dashboard() },
        ],
    },
});

const ingredientRows = ref<IngredientRow[]>([
    { kind: 'item', quantity_text: '', unit_text: '', name: '' },
]);
const stepRows = ref<StepRow[]>([{ kind: 'item', body: '' }]);

const form = useForm({
    title: '',
    source_url: '',
    servings: 2,
    cook_time_minutes: null as number | null,
    notes: '',
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
    form.post(storeRecipe().url, { forceFormData: true });
}

function onImage(event: Event): void {
    const target = event.target as HTMLInputElement;
    form.image = target.files?.[0] ?? null;
}

const inputClass =
    'w-full rounded-xl border border-rule bg-cream-soft px-4 py-2.5 text-sm outline-none transition placeholder:text-ink-faint focus:border-brand focus:ring-2 focus:ring-brand/30';
const labelClass = 'text-[11px] font-semibold uppercase tracking-[0.2em] text-ink-soft';
const pillBtn =
    'inline-flex items-center gap-1.5 rounded-full border border-rule bg-cream-soft px-3.5 py-1.5 text-xs font-medium transition hover:bg-ink/5 active:scale-[0.98]';
</script>

<template>
    <Head title="Nieuw recept" />

    <form class="flex flex-col gap-5 p-4 md:p-6" @submit.prevent="submit">
        <div class="rounded-3xl bg-brand p-6 text-ink md:p-8">
            <p class="text-[11px] font-semibold uppercase tracking-[0.22em] text-ink/65">
                Toevoegen
            </p>
            <h1 class="mt-1 font-display text-3xl leading-tight md:text-5xl">
                Nieuw recept
            </h1>
            <p class="mt-2 max-w-xl text-sm text-ink/75">
                Vul je recept netjes in. Geef het kopjes mee als 'Voor de saus' of
                'Voorbereiden' om delen te scheiden.
            </p>
        </div>

        <section class="rounded-3xl bg-cream-soft p-5 md:p-6">
            <h2 class="mb-4 font-display text-2xl leading-tight">Basis</h2>
            <div class="grid gap-4 md:grid-cols-2">
                <div class="flex flex-col gap-1.5 md:col-span-2">
                    <label for="title" :class="labelClass">Titel</label>
                    <input id="title" v-model="form.title" :class="inputClass" required />
                    <InputError :message="form.errors.title" />
                </div>

                <div class="flex flex-col gap-1.5">
                    <label for="servings" :class="labelClass">Personen</label>
                    <input
                        id="servings"
                        v-model.number="form.servings"
                        type="number"
                        min="1"
                        max="99"
                        required
                        :class="inputClass"
                    />
                    <InputError :message="form.errors.servings" />
                </div>

                <div class="flex flex-col gap-1.5">
                    <label for="cook_time" :class="labelClass">Kooktijd (min)</label>
                    <input
                        id="cook_time"
                        :value="form.cook_time_minutes ?? ''"
                        type="number"
                        min="0"
                        :class="inputClass"
                        @input="
                            (e: Event) => {
                                const v = (e.target as HTMLInputElement).value;
                                form.cook_time_minutes = v === '' ? null : Number(v);
                            }
                        "
                    />
                    <InputError :message="form.errors.cook_time_minutes" />
                </div>

                <div class="flex flex-col gap-1.5 md:col-span-2">
                    <label for="source_url" :class="labelClass">Bron URL (optioneel)</label>
                    <input id="source_url" v-model="form.source_url" type="url" :class="inputClass" />
                    <InputError :message="form.errors.source_url" />
                </div>

                <div class="flex flex-col gap-1.5 md:col-span-2">
                    <label :class="labelClass">Afbeelding (optioneel)</label>
                    <label
                        class="flex cursor-pointer items-center gap-3 rounded-xl border border-dashed border-rule bg-cream px-4 py-3 text-sm transition hover:bg-ink/5"
                    >
                        <ImagePlus class="size-4 text-ink-soft" />
                        <span class="flex-1 text-ink-soft">
                            {{ form.image ? form.image.name : 'Klik om een afbeelding te kiezen' }}
                        </span>
                        <input
                            type="file"
                            accept="image/*,.heic,.heif"
                            class="hidden"
                            @change="onImage"
                        />
                    </label>
                    <InputError :message="form.errors.image" />
                </div>
            </div>
        </section>

        <section class="rounded-3xl bg-cream-soft p-5 md:p-6">
            <div class="mb-4 flex items-center justify-between">
                <h2 class="font-display text-2xl leading-tight">Ingrediënten</h2>
                <div class="flex flex-wrap gap-2">
                    <button type="button" :class="pillBtn" @click="addIngredientHeader">
                        <Plus class="size-3.5" /> Kopje
                    </button>
                    <button type="button" :class="pillBtn" @click="addIngredientItem">
                        <Plus class="size-3.5" /> Regel
                    </button>
                </div>
            </div>

            <div class="flex flex-col gap-2">
                <template v-for="(row, idx) in ingredientRows" :key="`ing-${idx}`">
                    <div
                        v-if="row.kind === 'header'"
                        class="flex items-center gap-2 rounded-xl bg-block-lime px-3 py-2"
                    >
                        <input
                            v-model="row.name"
                            placeholder="Kopje, bv 'Voor de saus'"
                            class="w-full bg-transparent text-sm font-semibold uppercase tracking-[0.16em] outline-none placeholder:text-ink/50"
                        />
                        <button
                            type="button"
                            class="grid size-7 shrink-0 place-items-center rounded-full text-ink/70 transition hover:bg-ink/10"
                            @click="removeIngredientRow(idx)"
                        >
                            <X class="size-3.5" />
                        </button>
                    </div>
                    <div
                        v-else
                        class="grid grid-cols-[80px_90px_1fr_auto] items-center gap-2 sm:grid-cols-[100px_120px_1fr_auto]"
                    >
                        <input v-model="row.quantity_text" placeholder="200" :class="inputClass" />
                        <input v-model="row.unit_text" placeholder="g / el" :class="inputClass" />
                        <input v-model="row.name" placeholder="bloem" :class="inputClass" />
                        <button
                            type="button"
                            class="grid size-9 place-items-center rounded-full text-ink-faint transition hover:bg-ink/5 hover:text-warn"
                            @click="removeIngredientRow(idx)"
                        >
                            <X class="size-4" />
                        </button>
                    </div>
                </template>
            </div>
            <InputError class="mt-3" :message="form.errors.ingredients" />
        </section>

        <section class="rounded-3xl bg-cream-soft p-5 md:p-6">
            <div class="mb-4 flex items-center justify-between">
                <h2 class="font-display text-2xl leading-tight">Stappen</h2>
                <div class="flex flex-wrap gap-2">
                    <button type="button" :class="pillBtn" @click="addStepHeader">
                        <Plus class="size-3.5" /> Kopje
                    </button>
                    <button type="button" :class="pillBtn" @click="addStepItem">
                        <Plus class="size-3.5" /> Stap
                    </button>
                </div>
            </div>

            <div class="flex flex-col gap-3">
                <template v-for="(row, idx) in stepRows" :key="`step-${idx}`">
                    <div
                        v-if="row.kind === 'header'"
                        class="flex items-center gap-2 rounded-xl bg-block-sky px-3 py-2"
                    >
                        <input
                            v-model="row.name"
                            placeholder="Kopje, bv 'Voorbereiden'"
                            class="w-full bg-transparent text-sm font-semibold uppercase tracking-[0.16em] outline-none placeholder:text-ink/50"
                        />
                        <button
                            type="button"
                            class="grid size-7 shrink-0 place-items-center rounded-full text-ink/70 transition hover:bg-ink/10"
                            @click="removeStepRow(idx)"
                        >
                            <X class="size-3.5" />
                        </button>
                    </div>
                    <div v-else class="flex items-start gap-2">
                        <span
                            class="mt-1 grid size-9 shrink-0 place-items-center rounded-full bg-ink text-sm font-semibold text-cream tabular-nums"
                        >
                            {{ stepNumber(idx) }}
                        </span>
                        <textarea
                            v-model="row.body"
                            placeholder="Beschrijf de stap..."
                            class="min-h-[88px] flex-1 rounded-xl border border-rule bg-cream px-4 py-2.5 text-sm leading-relaxed outline-none transition placeholder:text-ink-faint focus:border-brand focus:ring-2 focus:ring-brand/30"
                        />
                        <button
                            type="button"
                            class="mt-1 grid size-9 place-items-center rounded-full text-ink-faint transition hover:bg-ink/5 hover:text-warn"
                            @click="removeStepRow(idx)"
                        >
                            <X class="size-4" />
                        </button>
                    </div>
                </template>
            </div>
            <InputError class="mt-3" :message="form.errors.steps" />
        </section>

        <section class="rounded-3xl bg-cream-soft p-5 md:p-6">
            <label for="notes" class="mb-2 block font-display text-2xl leading-tight">
                Notities
            </label>
            <textarea
                id="notes"
                v-model="form.notes"
                placeholder="Iets dat je niet wil vergeten..."
                class="min-h-[120px] w-full rounded-xl border border-rule bg-cream px-4 py-2.5 text-sm leading-relaxed outline-none transition placeholder:text-ink-faint focus:border-brand focus:ring-2 focus:ring-brand/30"
            />
            <InputError class="mt-2" :message="form.errors.notes" />
        </section>

        <div class="flex flex-wrap items-center justify-end gap-2">
            <Link
                :href="recipesIndex().url"
                class="inline-flex items-center gap-2 rounded-full border border-rule px-5 py-2.5 text-sm font-medium transition hover:bg-ink/5"
            >
                Annuleer
            </Link>
            <button
                type="submit"
                :disabled="form.processing"
                class="inline-flex items-center gap-2 rounded-full bg-ink px-6 py-2.5 text-sm font-medium text-cream transition active:scale-[0.98] hover:bg-[#3a2c24] disabled:opacity-50"
            >
                Opslaan
            </button>
        </div>
    </form>
</template>
