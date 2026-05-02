<script setup lang="ts">
import { Head, Link, router, usePage } from '@inertiajs/vue3';
import {
    ChefHat,
    ChevronDown,
    ClipboardPaste,
    Clock,
    Link2,
    PencilLine,
    Plus,
    Search,
    Star,
    X,
} from 'lucide-vue-next';
import { computed, ref, watch } from 'vue';
import RecipeController from '@/actions/App/Http/Controllers/RecipeController';
import ImportUrlDialog from '@/components/ImportUrlDialog.vue';
import PasteRecipeDialog from '@/components/PasteRecipeDialog.vue';
import {
    DropdownMenu,
    DropdownMenuContent,
    DropdownMenuItem,
    DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu';
import { dashboard } from '@/routes';
import { create as createRecipe, index as recipesIndex, show as showRecipe } from '@/routes/recipes';
import type { RecipeListFilters, RecipeSummary } from '@/types/recipes';

const props = defineProps<{
    recipes: RecipeSummary[];
    filters: RecipeListFilters;
}>();

defineOptions({
    layout: { breadcrumbs: [{ title: 'Recepten', href: dashboard() }] },
});

const page = usePage();
const userName = computed<string>(() => page.props.auth?.user?.name ?? 'kok');
const firstName = computed<string>(() => userName.value.split(' ')[0] ?? userName.value);

const urlOpen = ref<boolean>(false);
const pasteOpen = ref<boolean>(false);

const search = ref<string>(props.filters.q ?? '');
const starredOnly = ref<boolean>(!!props.filters.starred);
const cookedOnly = ref<boolean>(!!props.filters.cooked);
const timeBucket = ref<RecipeListFilters['time']>(props.filters.time ?? null);

let searchTimer: ReturnType<typeof setTimeout> | null = null;

function applyFilters(replace = false): void {
    const query: Record<string, string> = {};
    const q = search.value.trim();
    if (q !== '') query.q = q;
    if (starredOnly.value) query.starred = '1';
    if (cookedOnly.value) query.cooked = '1';
    if (timeBucket.value) query.time = timeBucket.value;

    router.get(recipesIndex().url, query, {
        preserveState: true,
        preserveScroll: true,
        replace,
        only: ['recipes', 'filters'],
    });
}

watch(search, () => {
    if (searchTimer) clearTimeout(searchTimer);
    searchTimer = setTimeout(() => applyFilters(true), 220);
});

watch([starredOnly, cookedOnly, timeBucket], () => applyFilters(true));

function clearAll(): void {
    search.value = '';
    starredOnly.value = false;
    cookedOnly.value = false;
    timeBucket.value = null;
}

const hasActiveFilter = computed<boolean>(
    () => search.value.trim() !== '' || starredOnly.value || cookedOnly.value || timeBucket.value !== null,
);

function setTime(value: RecipeListFilters['time']): void {
    timeBucket.value = timeBucket.value === value ? null : value;
}

function toggleStar(recipe: RecipeSummary, event: Event): void {
    event.preventDefault();
    event.stopPropagation();
    router.post(
        RecipeController.toggleStar.url(recipe.id),
        {},
        { preserveScroll: true, preserveState: true, only: ['recipes', 'filters'] },
    );
}

function lastCookedLabel(recipe: RecipeSummary): string | null {
    if (!recipe.last_cooked_at) return null;
    const date = new Date(recipe.last_cooked_at);
    const days = Math.round((Date.now() - date.getTime()) / 86_400_000);
    if (days < 1) return 'vandaag gekookt';
    if (days < 2) return 'gisteren gekookt';
    if (days < 14) return `${days}d geleden`;
    if (days < 60) return `${Math.round(days / 7)}w geleden`;
    return `${Math.round(days / 30)}mnd geleden`;
}

const tilePalette = ['lime', 'pink', 'sky', 'cream', 'accent', 'ink'] as const;
type Tile = (typeof tilePalette)[number];

function tileColor(index: number): Tile {
    return tilePalette[index % tilePalette.length];
}

const tileBgClass: Record<Tile, string> = {
    lime: 'bg-block-lime text-ink',
    pink: 'bg-block-pink text-ink',
    sky: 'bg-block-sky text-ink',
    cream: 'bg-cream-soft text-ink',
    accent: 'bg-brand text-ink',
    ink: 'bg-ink text-cream',
};
</script>

<template>
    <Head title="Mijn kookboek" />

    <div class="flex flex-col gap-5 p-4 md:gap-6 md:p-6">
        <div class="rounded-3xl bg-ink p-6 text-cream md:p-8">
            <div class="flex flex-wrap items-end justify-between gap-4">
                <div>
                    <p class="text-[11px] font-semibold uppercase tracking-[0.22em] text-cream/60">
                        Welkom terug
                    </p>
                    <h1 class="mt-1 font-display text-4xl leading-[1.05] tracking-tight md:text-5xl">
                        Hallo,
                        <span class="italic text-brand">{{ firstName }}!</span>
                    </h1>
                </div>
                <DropdownMenu>
                    <DropdownMenuTrigger as-child>
                        <button
                            type="button"
                            class="inline-flex items-center gap-2 rounded-full bg-brand px-5 py-2.5 text-sm font-medium text-ink transition active:scale-[0.98] hover:bg-[#d35a31]"
                        >
                            <Plus class="size-4" /> Recept toevoegen
                            <ChevronDown class="size-4 opacity-70" />
                        </button>
                    </DropdownMenuTrigger>
                    <DropdownMenuContent align="end" class="w-64">
                        <DropdownMenuItem @select="urlOpen = true">
                            <Link2 class="size-4" />
                            <div class="flex flex-col">
                                <span>Via URL</span>
                                <span class="text-xs text-muted-foreground">
                                    Receptenpagina van een blog
                                </span>
                            </div>
                        </DropdownMenuItem>
                        <DropdownMenuItem @select="pasteOpen = true">
                            <ClipboardPaste class="size-4" />
                            <div class="flex flex-col">
                                <span>Via geplakte tekst</span>
                                <span class="text-xs text-muted-foreground">
                                    Instagram, TikTok, mailtje, etc.
                                </span>
                            </div>
                        </DropdownMenuItem>
                        <DropdownMenuItem as-child>
                            <Link :href="createRecipe()">
                                <PencilLine class="size-4" />
                                <div class="flex flex-col">
                                    <span>Handmatig invoeren</span>
                                    <span class="text-xs text-muted-foreground">Eigen recept typen</span>
                                </div>
                            </Link>
                        </DropdownMenuItem>
                    </DropdownMenuContent>
                </DropdownMenu>
            </div>
        </div>

        <div class="flex flex-col gap-3">
            <label
                class="flex items-center gap-3 rounded-full border border-rule bg-cream-soft px-5 py-3 transition focus-within:border-brand focus-within:ring-2 focus-within:ring-brand/30"
            >
                <Search class="size-4 text-ink-faint" />
                <input
                    v-model="search"
                    type="search"
                    placeholder="Zoek op titel, ingrediënt of notitie..."
                    class="flex-1 bg-transparent text-sm outline-none placeholder:text-ink-faint"
                />
                <button
                    v-if="search"
                    type="button"
                    class="grid size-6 place-items-center rounded-full bg-ink/10 text-ink-soft transition hover:bg-ink/20"
                    @click="search = ''"
                >
                    <X class="size-3" />
                </button>
                <span class="rounded-full bg-ink/5 px-2 py-0.5 text-xs tabular-nums text-ink-soft">
                    {{ recipes.length }}
                </span>
            </label>

            <div class="flex flex-wrap items-center gap-2">
                <button
                    type="button"
                    :class="[
                        'inline-flex items-center gap-1.5 rounded-full border px-3.5 py-1.5 text-xs font-semibold transition',
                        starredOnly
                            ? 'border-brand bg-brand text-ink'
                            : 'border-rule bg-cream-soft text-ink-soft hover:bg-ink/5',
                    ]"
                    @click="starredOnly = !starredOnly"
                >
                    <Star class="size-3.5" :fill="starredOnly ? 'currentColor' : 'none'" />
                    Favorieten
                </button>
                <button
                    type="button"
                    :class="[
                        'inline-flex items-center gap-1.5 rounded-full border px-3.5 py-1.5 text-xs font-semibold transition',
                        cookedOnly
                            ? 'border-ink bg-ink text-cream'
                            : 'border-rule bg-cream-soft text-ink-soft hover:bg-ink/5',
                    ]"
                    @click="cookedOnly = !cookedOnly"
                >
                    <ChefHat class="size-3.5" />
                    Eerder gekookt
                </button>
                <span class="mx-1 h-5 w-px bg-rule" />
                <button
                    v-for="opt in [
                        { key: 'quick', label: '< 20 min' },
                        { key: 'medium', label: '20–45 min' },
                        { key: 'long', label: '> 45 min' },
                    ] as const"
                    :key="opt.key"
                    type="button"
                    :class="[
                        'inline-flex items-center gap-1.5 rounded-full border px-3.5 py-1.5 text-xs font-semibold transition',
                        timeBucket === opt.key
                            ? 'border-ink bg-ink text-cream'
                            : 'border-rule bg-cream-soft text-ink-soft hover:bg-ink/5',
                    ]"
                    @click="setTime(opt.key)"
                >
                    <Clock class="size-3.5" />
                    {{ opt.label }}
                </button>
                <button
                    v-if="hasActiveFilter"
                    type="button"
                    class="ml-auto inline-flex items-center gap-1.5 rounded-full px-3 py-1.5 text-xs font-semibold text-ink-soft transition hover:bg-ink/5"
                    @click="clearAll"
                >
                    <X class="size-3.5" /> wissen
                </button>
            </div>
        </div>

        <div
            v-if="recipes.length === 0"
            class="rounded-3xl border border-dashed border-rule bg-cream-soft p-12 text-center"
        >
            <div class="mx-auto mb-4 flex size-12 items-center justify-center rounded-full bg-ink text-cream">
                <ChefHat class="size-5" />
            </div>
            <h2 class="font-display text-2xl">
                {{ hasActiveFilter ? 'Niets gevonden.' : 'Je kookboek is nog leeg.' }}
            </h2>
            <p class="mt-2 text-sm text-ink-soft">
                {{
                    hasActiveFilter
                        ? 'Pas je filters aan of zoek op een ander woord.'
                        : 'Klik op "Recept toevoegen" en plak een link, een caption of begin handmatig.'
                }}
            </p>
        </div>

        <div
            v-else
            class="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4"
        >
            <Link
                v-for="(recipe, idx) in recipes"
                :key="recipe.id"
                :href="showRecipe(recipe.id)"
                class="group flex flex-col overflow-hidden rounded-3xl bg-cream-soft transition hover:-translate-y-1 hover:shadow-tile-hover"
            >
                <div class="relative aspect-[4/3] overflow-hidden bg-ink/5">
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
                    <div class="absolute left-3 top-3 flex flex-wrap gap-1.5">
                        <span
                            v-if="recipe.cook_time_minutes"
                            class="flex items-center gap-1 rounded-full bg-cream-soft/95 px-2.5 py-1 text-[11px] font-semibold tabular-nums backdrop-blur"
                        >
                            <Clock class="size-3" /> {{ recipe.cook_time_minutes }}m
                        </span>
                        <span
                            v-if="recipe.cooked_count > 0"
                            class="flex items-center gap-1 rounded-full bg-ink/90 px-2.5 py-1 text-[11px] font-semibold tabular-nums text-cream backdrop-blur"
                        >
                            <ChefHat class="size-3" /> ×{{ recipe.cooked_count }}
                        </span>
                    </div>
                    <button
                        type="button"
                        :aria-label="recipe.is_starred ? 'Ster verwijderen' : 'Markeer als favoriet'"
                        :class="[
                            'absolute right-3 top-3 grid size-9 place-items-center rounded-full backdrop-blur transition active:scale-90',
                            recipe.is_starred
                                ? 'bg-brand text-ink'
                                : 'bg-cream-soft/90 text-ink-soft hover:bg-cream-soft',
                        ]"
                        @click="toggleStar(recipe, $event)"
                    >
                        <Star class="size-4" :fill="recipe.is_starred ? 'currentColor' : 'none'" />
                    </button>
                </div>
                <div :class="['flex flex-1 flex-col gap-2 p-5', tileBgClass[tileColor(idx)]]">
                    <div class="flex items-end gap-3">
                        <h3 class="line-clamp-2 flex-1 font-display text-lg leading-tight">
                            {{ recipe.title }}
                        </h3>
                        <span
                            :class="[
                                'grid size-8 shrink-0 place-items-center rounded-full transition group-hover:rotate-12',
                                tileColor(idx) === 'ink'
                                    ? 'bg-cream text-ink'
                                    : 'bg-ink text-cream',
                            ]"
                        >
                            <Plus class="size-4 rotate-45" />
                        </span>
                    </div>
                    <p
                        v-if="lastCookedLabel(recipe)"
                        class="text-[11px] font-semibold uppercase tracking-[0.18em] opacity-70"
                    >
                        {{ lastCookedLabel(recipe) }}
                    </p>
                </div>
            </Link>
        </div>

        <ImportUrlDialog v-model:open="urlOpen" />
        <PasteRecipeDialog v-model:open="pasteOpen" />
    </div>
</template>
