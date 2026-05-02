<script setup lang="ts">
import { Head, useForm } from '@inertiajs/vue3';
import { Plus, X } from 'lucide-vue-next';
import Heading from '@/components/Heading.vue';
import InputError from '@/components/InputError.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
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

type IngredientRow = { quantity_text: string; unit_text: string; name: string };
type StepRow = { body: string };

const form = useForm({
    title: '',
    source_url: '',
    servings: 2,
    cook_time_minutes: null as number | null,
    notes: '',
    image: null as File | null,
    ingredients: [
        { quantity_text: '', unit_text: '', name: '' } as IngredientRow,
    ],
    steps: [{ body: '' } as StepRow],
});

function addIngredient(): void {
    form.ingredients.push({ quantity_text: '', unit_text: '', name: '' });
}

function removeIngredient(index: number): void {
    form.ingredients.splice(index, 1);
    if (form.ingredients.length === 0) {
        addIngredient();
    }
}

function addStep(): void {
    form.steps.push({ body: '' });
}

function removeStep(index: number): void {
    form.steps.splice(index, 1);
    if (form.steps.length === 0) {
        addStep();
    }
}

function submit(): void {
    form.post(storeRecipe().url, { forceFormData: true });
}

function onImage(event: Event): void {
    const target = event.target as HTMLInputElement;
    form.image = target.files?.[0] ?? null;
}
</script>

<template>
    <Head title="Nieuw recept" />

    <form class="flex flex-col gap-6 p-4 md:p-6" @submit.prevent="submit">
        <Heading title="Nieuw recept" description="Vul je recept in" />

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
                <InputError :message="form.errors.source_url" />
            </div>

            <div class="grid gap-2 md:col-span-2">
                <Label for="image">Afbeelding (optioneel)</Label>
                <Input id="image" type="file" accept="image/*" @change="onImage" />
                <InputError :message="form.errors.image" />
            </div>
        </div>

        <section class="flex flex-col gap-3">
            <div class="flex items-center justify-between">
                <h2 class="font-semibold">Ingrediënten</h2>
                <Button type="button" variant="outline" size="sm" @click="addIngredient">
                    <Plus class="size-4" /> Regel
                </Button>
            </div>
            <div
                v-for="(row, idx) in form.ingredients"
                :key="idx"
                class="grid grid-cols-[80px_90px_1fr_auto] gap-2 sm:grid-cols-[100px_120px_1fr_auto]"
            >
                <Input v-model="row.quantity_text" placeholder="200" />
                <Input v-model="row.unit_text" placeholder="g / el" />
                <Input v-model="row.name" placeholder="bloem" required />
                <Button type="button" variant="ghost" size="icon" @click="removeIngredient(idx)">
                    <X class="size-4" />
                </Button>
            </div>
            <InputError :message="form.errors.ingredients" />
        </section>

        <section class="flex flex-col gap-3">
            <div class="flex items-center justify-between">
                <h2 class="font-semibold">Stappen</h2>
                <Button type="button" variant="outline" size="sm" @click="addStep">
                    <Plus class="size-4" /> Stap
                </Button>
            </div>
            <div
                v-for="(row, idx) in form.steps"
                :key="idx"
                class="flex items-start gap-2"
            >
                <span class="mt-3 size-7 shrink-0 rounded-full bg-muted text-center leading-7 text-sm font-medium">
                    {{ idx + 1 }}
                </span>
                <textarea
                    v-model="row.body"
                    class="min-h-[72px] flex-1 rounded-md border border-input bg-transparent px-3 py-2 text-sm shadow-xs outline-none focus-visible:border-ring focus-visible:ring-2 focus-visible:ring-ring/50"
                    placeholder="Beschrijf de stap..."
                    required
                />
                <Button type="button" variant="ghost" size="icon" @click="removeStep(idx)">
                    <X class="size-4" />
                </Button>
            </div>
            <InputError :message="form.errors.steps" />
        </section>

        <section class="grid gap-2">
            <Label for="notes">Notities</Label>
            <textarea
                id="notes"
                v-model="form.notes"
                class="min-h-[88px] rounded-md border border-input bg-transparent px-3 py-2 text-sm shadow-xs outline-none focus-visible:border-ring focus-visible:ring-2 focus-visible:ring-ring/50"
            />
        </section>

        <div class="flex justify-end">
            <Button type="submit" :disabled="form.processing">Opslaan</Button>
        </div>
    </form>
</template>
