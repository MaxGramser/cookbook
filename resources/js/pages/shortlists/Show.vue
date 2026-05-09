<script setup lang="ts">
import { Form, Head, router } from '@inertiajs/vue3';
import {
    ChefHat,
    GripVertical,
    NotebookPen,
    Pencil,
    ShoppingBasket,
    SquarePen,
    Trash2,
    X,
} from 'lucide-vue-next';
import { computed, nextTick, ref, watch } from 'vue';
import GrocerySessionController from '@/actions/App/Http/Controllers/GrocerySessionController';
import ShortlistController from '@/actions/App/Http/Controllers/ShortlistController';
import RecipeCard from '@/components/RecipeCard.vue';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { dashboard } from '@/routes';
import type {
    RecipeSummary,
    ShortlistDetail,
    ShortlistRecipe,
} from '@/types/recipes';

const props = defineProps<{
    shortlist: ShortlistDetail;
}>();

defineOptions({
    layout: { breadcrumbs: [{ title: 'Recepten', href: dashboard() }] },
});

const recipes = ref<ShortlistRecipe[]>([...props.shortlist.recipes]);

watch(
    () => props.shortlist.recipes,
    (next) => {
        recipes.value = [...next];
    },
);

const draggedIndex = ref<number | null>(null);
const dragOverIndex = ref<number | null>(null);

const expandedNotes = ref<Set<number>>(new Set());

function toggleExpanded(recipeId: number): void {
    const next = new Set(expandedNotes.value);
    if (next.has(recipeId)) {
        next.delete(recipeId);
    } else {
        next.add(recipeId);
    }
    expandedNotes.value = next;
}

const renameOpen = ref<boolean>(false);
const renameValue = ref<string>(props.shortlist.name);

const noteRecipe = ref<ShortlistRecipe | null>(null);
const noteValue = ref<string>('');

const colorClass: Record<string, string> = {
    lime: 'bg-block-lime',
    pink: 'bg-block-pink',
    sky: 'bg-block-sky',
    cream: 'bg-cream-soft',
    accent: 'bg-brand',
    ink: 'bg-ink text-cream',
};

const heroBg = computed<string>(() => {
    const c = props.shortlist.color;

    return c ? (colorClass[c] ?? 'bg-cream-soft') : 'bg-cream-soft';
});

const isDarkHero = computed<boolean>(() => props.shortlist.color === 'ink');

function recipeIdFromEvent(event: DragEvent): number | null {
    const id = event.dataTransfer?.getData('application/x-recipe-id');

    if (!id) {
        return null;
    }

    const parsed = Number(id);

    return Number.isFinite(parsed) && parsed > 0 ? parsed : null;
}

function onCardDragStart(recipe: RecipeSummary, event: DragEvent): void {
    const id = recipeIdFromEvent(event);

    if (id === null) {
        const idx = recipes.value.findIndex((r) => r.id === recipe.id);

        if (idx !== -1) {
            draggedIndex.value = idx;
        }

        return;
    }

    draggedIndex.value = recipes.value.findIndex((r) => r.id === id);
}

function onCardDragEnd(): void {
    draggedIndex.value = null;
    dragOverIndex.value = null;
}

function onHandleDragStart(
    idx: number,
    recipe: ShortlistRecipe,
    event: DragEvent,
): void {
    if (event.dataTransfer) {
        event.dataTransfer.effectAllowed = 'copyMove';
        event.dataTransfer.setData(
            'application/x-recipe-id',
            String(recipe.id),
        );
        event.dataTransfer.setData('text/plain', recipe.title);
    }

    draggedIndex.value = idx;
}

function onSlotDragOver(index: number, event: DragEvent): void {
    if (draggedIndex.value === null) {
        return;
    }

    event.preventDefault();

    if (event.dataTransfer) {
        event.dataTransfer.dropEffect = 'move';
    }

    dragOverIndex.value = index;
}

function onSlotDrop(index: number, event: DragEvent): void {
    event.preventDefault();

    if (draggedIndex.value === null || draggedIndex.value === index) {
        dragOverIndex.value = null;

        return;
    }

    const next = [...recipes.value];
    const [moved] = next.splice(draggedIndex.value, 1);
    const targetIndex = draggedIndex.value < index ? index - 1 : index;
    next.splice(targetIndex, 0, moved);
    recipes.value = next;
    draggedIndex.value = null;
    dragOverIndex.value = null;

    persistOrder();
}

function persistOrder(): void {
    router.post(
        ShortlistController.reorder.url(props.shortlist.id),
        { recipe_ids: recipes.value.map((r) => r.id) },
        { preserveScroll: true, preserveState: true, only: [] },
    );
}

function removeRecipe(recipe: RecipeSummary): void {
    if (!window.confirm(`"${recipe.title}" uit deze shortlist verwijderen?`)) {
        return;
    }

    recipes.value = recipes.value.filter((r) => r.id !== recipe.id);

    router.delete(
        ShortlistController.detach.url({
            shortlist: props.shortlist.id,
            recipe: recipe.id,
        }),
        {
            preserveScroll: true,
            preserveState: true,
            only: ['shortlist', 'shortlists'],
        },
    );
}

function openNote(recipe: ShortlistRecipe): void {
    noteRecipe.value = recipe;
    noteValue.value = recipe.pivot.note ?? '';
}

function saveNote(): void {
    if (!noteRecipe.value) {
        return;
    }

    const recipeId = noteRecipe.value.id;
    const value = noteValue.value.trim();
    const idx = recipes.value.findIndex((r) => r.id === recipeId);

    if (idx !== -1) {
        recipes.value[idx] = {
            ...recipes.value[idx],
            pivot: {
                ...recipes.value[idx].pivot,
                note: value === '' ? null : value,
            },
        };
    }

    router.patch(
        ShortlistController.updateRecipe.url({
            shortlist: props.shortlist.id,
            recipe: recipeId,
        }),
        { note: value === '' ? null : value },
        { preserveScroll: true, preserveState: true, only: ['shortlist'] },
    );
    noteRecipe.value = null;
}

function openRename(): void {
    renameValue.value = props.shortlist.name;
    renameOpen.value = true;
    nextTick();
}

function saveRename(): void {
    const next = renameValue.value.trim();

    if (next === '' || next === props.shortlist.name) {
        renameOpen.value = false;

        return;
    }

    router.patch(
        ShortlistController.update.url(props.shortlist.id),
        { name: next, color: props.shortlist.color },
        {
            preserveScroll: true,
            onSuccess: () => {
                renameOpen.value = false;
            },
        },
    );
}

function confirmDelete(event: Event): void {
    if (!window.confirm(`Shortlist "${props.shortlist.name}" verwijderen?`)) {
        event.preventDefault();
    }
}
</script>

<template>
    <Head :title="shortlist.name" />

    <div class="flex flex-col gap-5 p-4 md:gap-6 md:p-6">
        <div
            :class="[
                'rounded-3xl p-6 md:p-8',
                heroBg,
                isDarkHero ? 'text-cream' : 'text-ink',
            ]"
        >
            <div class="flex flex-wrap items-end justify-between gap-4">
                <div class="min-w-0 flex-1">
                    <p
                        :class="[
                            'text-[11px] font-semibold tracking-[0.22em] uppercase',
                            isDarkHero ? 'text-cream/60' : 'text-ink/60',
                        ]"
                    >
                        Shortlist
                    </p>
                    <h1
                        class="font-display mt-1 line-clamp-2 text-4xl leading-[1.05] tracking-tight md:text-5xl"
                    >
                        {{ shortlist.name }}
                    </h1>
                    <p
                        :class="[
                            'mt-2 text-sm',
                            isDarkHero ? 'text-cream/70' : 'text-ink/70',
                        ]"
                    >
                        {{ recipes.length }}
                        {{ recipes.length === 1 ? 'recept' : 'recepten' }}
                        — sleep recepten vanuit Recepten om toe te voegen, sleep
                        tussen kaarten om te herorderen.
                    </p>
                </div>
                <div class="flex flex-wrap items-center gap-2">
                    <Form
                        v-bind="
                            GrocerySessionController.storeForShortlist.form(
                                shortlist.id,
                            )
                        "
                        v-slot="{ processing }"
                    >
                        <button
                            type="submit"
                            :disabled="processing || recipes.length === 0"
                            class="inline-flex items-center gap-2 rounded-full bg-brand px-5 py-2.5 text-sm font-medium text-ink transition hover:bg-[#d35a31] active:scale-[0.98] disabled:opacity-40"
                        >
                            <ShoppingBasket class="size-4" /> Boodschappen
                        </button>
                    </Form>
                    <button
                        type="button"
                        :class="[
                            'inline-flex items-center gap-2 rounded-full border px-5 py-2.5 text-sm font-medium transition',
                            isDarkHero
                                ? 'border-cream/25 hover:bg-cream/10'
                                : 'border-ink/15 hover:bg-ink/5',
                        ]"
                        @click="openRename"
                    >
                        <Pencil class="size-4" /> Hernoem
                    </button>
                </div>
            </div>
        </div>

        <div
            v-if="recipes.length === 0"
            class="rounded-3xl border border-dashed border-rule bg-cream-soft p-12 text-center"
        >
            <div
                class="mx-auto mb-4 flex size-12 items-center justify-center rounded-full bg-ink text-cream"
            >
                <ChefHat class="size-5" />
            </div>
            <h2 class="font-display text-2xl">
                Nog geen recepten in deze shortlist.
            </h2>
            <p class="mt-2 text-sm text-ink-soft">
                Sleep een recept hierheen vanuit de Recepten-pagina, of gebruik
                de "Op shortlist"-knop op een receptpagina.
            </p>
        </div>

        <div
            v-else
            class="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4"
        >
            <div
                v-for="(recipe, idx) in recipes"
                :key="recipe.id"
                class="relative flex flex-col"
                @dragover="onSlotDragOver(idx, $event)"
                @drop="onSlotDrop(idx, $event)"
                @dragleave="dragOverIndex = null"
            >
                <div
                    aria-hidden="true"
                    class="pointer-events-none absolute inset-y-2 -left-2 hidden w-1 rounded-full transition sm:block"
                    :class="
                        dragOverIndex === idx ? 'bg-brand' : 'bg-transparent'
                    "
                ></div>
                <div
                    v-if="idx === recipes.length - 1"
                    aria-hidden="true"
                    class="pointer-events-none absolute inset-y-2 -right-2 hidden w-1 rounded-full transition sm:block"
                    :class="
                        dragOverIndex === recipes.length
                            ? 'bg-brand'
                            : 'bg-transparent'
                    "
                ></div>
                <RecipeCard
                    :recipe="recipe"
                    :index="idx"
                    :draggable="true"
                    :show-star="false"
                    :show-remove="false"
                    image-aspect="aspect-[16/9]"
                    @dragstart="onCardDragStart"
                    @dragend="onCardDragEnd"
                />
                <div
                    class="mt-2 flex items-start gap-2 rounded-2xl border border-rule bg-cream-soft px-3 py-2"
                >
                    <span
                        draggable="true"
                        class="grid size-7 shrink-0 cursor-grab place-items-center rounded-full text-ink-faint transition hover:bg-ink/5 hover:text-ink active:cursor-grabbing"
                        title="Sleep om te herorderen"
                        @dragstart="onHandleDragStart(idx, recipe, $event)"
                        @dragend="onCardDragEnd"
                    >
                        <GripVertical class="size-4" />
                    </span>
                    <button
                        type="button"
                        class="flex flex-1 items-start gap-2 rounded-xl px-2 py-1 text-left text-sm transition hover:bg-ink/5"
                        @click="
                            recipe.pivot.note
                                ? toggleExpanded(recipe.id)
                                : openNote(recipe)
                        "
                    >
                        <NotebookPen
                            class="mt-0.5 size-4 shrink-0"
                            :class="
                                recipe.pivot.note ? 'text-ink' : 'text-ink-faint'
                            "
                        />
                        <span
                            v-if="recipe.pivot.note"
                            class="text-ink"
                            :class="
                                expandedNotes.has(recipe.id)
                                    ? 'whitespace-pre-wrap'
                                    : 'line-clamp-1'
                            "
                        >
                            {{ recipe.pivot.note }}
                        </span>
                        <span v-else class="text-ink-faint italic">
                            + notitie toevoegen
                        </span>
                    </button>
                    <button
                        v-if="recipe.pivot.note"
                        type="button"
                        aria-label="Notitie bewerken"
                        class="grid size-7 shrink-0 place-items-center rounded-full text-ink-faint transition hover:bg-ink/5 hover:text-ink"
                        @click="openNote(recipe)"
                    >
                        <SquarePen class="size-4" />
                    </button>
                    <button
                        type="button"
                        aria-label="Uit shortlist verwijderen"
                        class="grid size-7 shrink-0 place-items-center rounded-full text-ink-faint transition hover:bg-warn/10 hover:text-warn"
                        @click="removeRecipe(recipe)"
                    >
                        <X class="size-4" />
                    </button>
                </div>
            </div>
        </div>

        <Form
            v-bind="ShortlistController.destroy.form(shortlist.id)"
            v-slot="{ processing }"
            @submit="confirmDelete"
        >
            <button
                type="submit"
                :disabled="processing"
                class="inline-flex items-center gap-2 rounded-full border border-warn/30 px-5 py-2.5 text-sm font-medium text-warn transition hover:bg-warn/5 disabled:opacity-50"
            >
                <Trash2 class="size-4" /> Shortlist verwijderen
            </button>
        </Form>
    </div>

    <Dialog v-model:open="renameOpen">
        <DialogContent class="sm:max-w-md">
            <DialogHeader>
                <DialogTitle>Shortlist hernoemen</DialogTitle>
                <DialogDescription
                    >Geef je shortlist een nieuwe naam.</DialogDescription
                >
            </DialogHeader>
            <form class="flex flex-col gap-3" @submit.prevent="saveRename">
                <div class="grid gap-2">
                    <Label for="rename-input">Naam</Label>
                    <Input
                        id="rename-input"
                        v-model="renameValue"
                        autofocus
                        required
                    />
                </div>
                <DialogFooter class="mt-2">
                    <Button
                        type="button"
                        variant="ghost"
                        @click="renameOpen = false"
                        >Annuleer</Button
                    >
                    <Button type="submit">Opslaan</Button>
                </DialogFooter>
            </form>
        </DialogContent>
    </Dialog>

    <Dialog
        :open="noteRecipe !== null"
        @update:open="(val) => (noteRecipe = val ? noteRecipe : null)"
    >
        <DialogContent class="sm:max-w-md">
            <DialogHeader>
                <DialogTitle>Notitie</DialogTitle>
                <DialogDescription>
                    Een korte notitie bij dit recept binnen deze shortlist (bv.
                    "voorgerecht", "halveren").
                </DialogDescription>
            </DialogHeader>
            <form class="flex flex-col gap-3" @submit.prevent="saveNote">
                <div class="grid gap-2">
                    <Label for="note-input">Notitie</Label>
                    <textarea
                        id="note-input"
                        v-model="noteValue"
                        rows="3"
                        maxlength="500"
                        class="w-full rounded-xl border border-rule bg-cream-soft px-3 py-2 text-sm outline-none focus:border-brand focus:ring-2 focus:ring-brand/30"
                    ></textarea>
                </div>
                <DialogFooter class="mt-2">
                    <Button
                        type="button"
                        variant="ghost"
                        @click="noteRecipe = null"
                        >Annuleer</Button
                    >
                    <Button type="submit">Opslaan</Button>
                </DialogFooter>
            </form>
        </DialogContent>
    </Dialog>
</template>
