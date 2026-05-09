<script setup lang="ts">
import { Link, router } from '@inertiajs/vue3';
import { ChefHat, Clock, Star, X } from 'lucide-vue-next';
import { computed } from 'vue';
import RecipeController from '@/actions/App/Http/Controllers/RecipeController';
import { show as showRecipe } from '@/routes/recipes';
import type { RecipeSummary } from '@/types/recipes';

type Tile = 'lime' | 'pink' | 'sky' | 'cream' | 'accent' | 'ink';

const tilePalette: readonly Tile[] = [
    'lime',
    'pink',
    'sky',
    'cream',
    'accent',
    'ink',
];

const tileBgClass: Record<Tile, string> = {
    lime: 'bg-block-lime text-ink',
    pink: 'bg-block-pink text-ink',
    sky: 'bg-block-sky text-ink',
    cream: 'bg-cream-soft text-ink',
    accent: 'bg-brand text-ink',
    ink: 'bg-ink text-cream',
};

const props = withDefaults(
    defineProps<{
        recipe: RecipeSummary;
        index?: number;
        tile?: Tile;
        draggable?: boolean;
        showStar?: boolean;
        showRemove?: boolean;
        lastCookedLabel?: string | null;
        imageAspect?: string;
    }>(),
    {
        index: 0,
        tile: undefined,
        draggable: false,
        showStar: true,
        showRemove: false,
        lastCookedLabel: null,
        imageAspect: 'aspect-[4/3]',
    },
);

const emit = defineEmits<{
    (e: 'remove', recipe: RecipeSummary): void;
    (e: 'dragstart', recipe: RecipeSummary, event: DragEvent): void;
    (e: 'dragend', recipe: RecipeSummary, event: DragEvent): void;
}>();

const tile = computed<Tile>(
    () => props.tile ?? tilePalette[props.index % tilePalette.length],
);

function toggleStar(event: Event): void {
    event.preventDefault();
    event.stopPropagation();
    router.post(
        RecipeController.toggleStar.url(props.recipe.id),
        {},
        { preserveScroll: true, preserveState: false },
    );
}

function onRemove(event: Event): void {
    event.preventDefault();
    event.stopPropagation();
    emit('remove', props.recipe);
}

function onDragStart(event: DragEvent): void {
    if (!props.draggable) {
        return;
    }

    if (event.dataTransfer) {
        event.dataTransfer.effectAllowed = 'copyMove';
        event.dataTransfer.setData(
            'application/x-recipe-id',
            String(props.recipe.id),
        );
        event.dataTransfer.setData('text/plain', props.recipe.title);
    }

    emit('dragstart', props.recipe, event);
}

function onDragEnd(event: DragEvent): void {
    if (!props.draggable) {
        return;
    }

    emit('dragend', props.recipe, event);
}
</script>

<template>
    <Link
        :href="showRecipe(recipe.id)"
        :draggable="draggable"
        class="group hover:shadow-tile-hover flex flex-col overflow-hidden rounded-3xl bg-cream-soft transition hover:-translate-y-1"
        :class="draggable && 'cursor-grab active:cursor-grabbing'"
        @dragstart="onDragStart"
        @dragend="onDragEnd"
    >
        <div :class="['relative overflow-hidden bg-ink/5', imageAspect]">
            <img
                v-if="recipe.image_path"
                :src="`/storage/${recipe.image_path}`"
                :alt="recipe.title"
                class="h-full w-full object-cover transition duration-500 group-hover:scale-[1.04]"
            />
            <div
                v-else
                class="flex h-full w-full items-center justify-center text-ink-faint"
            >
                <ChefHat class="size-10" />
            </div>
            <div class="absolute top-3 left-3 flex flex-wrap gap-1.5">
                <span
                    v-if="recipe.cook_time_minutes"
                    class="flex items-center gap-1 rounded-full bg-cream-soft/95 px-2.5 py-1 text-[11px] font-semibold tabular-nums backdrop-blur"
                >
                    <Clock class="size-3" /> {{ recipe.cook_time_minutes }}m
                </span>
                <span
                    v-if="recipe.cooked_count > 0"
                    class="flex items-center gap-1 rounded-full bg-ink/90 px-2.5 py-1 text-[11px] font-semibold text-cream tabular-nums backdrop-blur"
                >
                    <ChefHat class="size-3" /> ×{{ recipe.cooked_count }}
                </span>
            </div>
            <button
                v-if="showRemove"
                type="button"
                aria-label="Verwijder uit shortlist"
                class="absolute top-3 right-3 grid size-9 place-items-center rounded-full bg-ink/85 text-cream backdrop-blur transition hover:bg-warn active:scale-90"
                @click="onRemove"
            >
                <X class="size-4" />
            </button>
            <button
                v-else-if="showStar"
                type="button"
                :aria-label="
                    recipe.is_starred
                        ? 'Ster verwijderen'
                        : 'Markeer als favoriet'
                "
                :class="[
                    'absolute top-3 right-3 grid size-9 place-items-center rounded-full backdrop-blur transition active:scale-90',
                    recipe.is_starred
                        ? 'bg-brand text-ink'
                        : 'bg-cream-soft/90 text-ink-soft hover:bg-cream-soft',
                ]"
                @click="toggleStar"
            >
                <Star
                    class="size-4"
                    :fill="recipe.is_starred ? 'currentColor' : 'none'"
                />
            </button>
        </div>
        <div :class="['flex flex-1 flex-col gap-2 p-5', tileBgClass[tile]]">
            <h3 class="font-display line-clamp-2 text-lg leading-tight">
                {{ recipe.title }}
            </h3>
            <div
                v-if="recipe.tags && recipe.tags.length > 0"
                class="flex flex-wrap gap-1"
            >
                <span
                    v-for="tag in recipe.tags.slice(0, 3)"
                    :key="tag.id"
                    class="rounded-full bg-ink/10 px-2 py-0.5 text-[10px] font-semibold tracking-[0.08em] uppercase"
                >
                    {{ tag.name }}
                </span>
                <span
                    v-if="recipe.tags.length > 3"
                    class="rounded-full bg-ink/10 px-2 py-0.5 text-[10px] font-semibold tabular-nums"
                >
                    +{{ recipe.tags.length - 3 }}
                </span>
            </div>
            <p
                v-if="lastCookedLabel"
                class="text-[11px] font-semibold tracking-[0.18em] uppercase opacity-70"
            >
                {{ lastCookedLabel }}
            </p>
        </div>
    </Link>
</template>
