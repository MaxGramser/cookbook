<script setup lang="ts">
import { Head, InfiniteScroll, Link, router, usePage } from '@inertiajs/vue3';
import {
    ChefHat,
    ChevronDown,
    ClipboardPaste,
    Clock,
    Link2,
    Loader2,
    PencilLine,
    Plus,
    Search,
    SlidersHorizontal,
    Star,
    Tag as TagIcon,
    X,
} from 'lucide-vue-next';
import { computed, ref, watch } from 'vue';
import ImportUrlDialog from '@/components/ImportUrlDialog.vue';
import PasteRecipeDialog from '@/components/PasteRecipeDialog.vue';
import RecipeCard from '@/components/RecipeCard.vue';
import TagChipSelect from '@/components/TagChipSelect.vue';
import {
    DropdownMenu,
    DropdownMenuContent,
    DropdownMenuItem,
    DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu';
import { dashboard } from '@/routes';
import {
    create as createRecipe,
    index as recipesIndex,
} from '@/routes/recipes';
import type {
    Paginated,
    RecipeListFilters,
    RecipeSummary,
    Tag,
    TagColor,
} from '@/types/recipes';

const props = defineProps<{
    recipes: Paginated<RecipeSummary>;
    filters: RecipeListFilters;
    tags: Tag[];
}>();

defineOptions({
    layout: { breadcrumbs: [{ title: 'Recepten', href: dashboard() }] },
});

const page = usePage();
const userName = computed<string>(() => page.props.auth?.user?.name ?? 'kok');
const firstName = computed<string>(
    () => userName.value.split(' ')[0] ?? userName.value,
);

const urlOpen = ref<boolean>(false);
const pasteOpen = ref<boolean>(false);

const search = ref<string>(props.filters.q ?? '');
const starredOnly = ref<boolean>(!!props.filters.starred);
const cookedOnly = ref<boolean>(!!props.filters.cooked);
const timeBucket = ref<RecipeListFilters['time']>(props.filters.time ?? null);
const selectedTagIds = ref<number[]>([...(props.filters.tag_ids ?? [])]);
const tagsOpen = ref<boolean>(selectedTagIds.value.length > 0);

let searchTimer: ReturnType<typeof setTimeout> | null = null;

function applyFilters(replace = false): void {
    const query: Record<string, string> = {};
    const q = search.value.trim();

    if (q !== '') {
        query.q = q;
    }

    if (starredOnly.value) {
        query.starred = '1';
    }

    if (cookedOnly.value) {
        query.cooked = '1';
    }

    if (timeBucket.value) {
        query.time = timeBucket.value;
    }

    if (selectedTagIds.value.length > 0) {
        query.tags = selectedTagIds.value.join(',');
    }

    router.get(recipesIndex().url, query, {
        preserveState: true,
        preserveScroll: true,
        replace,
    });
}

watch(search, () => {
    if (searchTimer) {
        clearTimeout(searchTimer);
    }

    searchTimer = setTimeout(() => applyFilters(true), 220);
});

watch([starredOnly, cookedOnly, timeBucket], () => applyFilters(true));
watch(selectedTagIds, () => applyFilters(true), { deep: true });

function clearAll(): void {
    search.value = '';
    starredOnly.value = false;
    cookedOnly.value = false;
    timeBucket.value = null;
    selectedTagIds.value = [];
}

const hasActiveFilter = computed<boolean>(
    () =>
        search.value.trim() !== '' ||
        starredOnly.value ||
        cookedOnly.value ||
        timeBucket.value !== null ||
        selectedTagIds.value.length > 0,
);

const selectedTags = computed<Tag[]>(() =>
    props.tags.filter((t) => selectedTagIds.value.includes(t.id)),
);

function removeTag(id: number): void {
    selectedTagIds.value = selectedTagIds.value.filter((tid) => tid !== id);
}

const tagPillClass: Record<TagColor, string> = {
    cream: 'bg-cream text-ink border-rule',
    lime: 'bg-block-lime text-ink border-transparent',
    pink: 'bg-block-pink text-ink border-transparent',
    sky: 'bg-block-sky text-ink border-transparent',
    accent: 'bg-brand text-ink border-transparent',
    ink: 'bg-ink text-cream border-transparent',
};

function setTime(value: RecipeListFilters['time']): void {
    timeBucket.value = timeBucket.value === value ? null : value;
}

function lastCookedLabel(recipe: RecipeSummary): string | null {
    if (!recipe.last_cooked_at) {
        return null;
    }

    const date = new Date(recipe.last_cooked_at);
    const days = Math.round((Date.now() - date.getTime()) / 86_400_000);

    if (days < 1) {
        return 'vandaag gekookt';
    }

    if (days < 2) {
        return 'gisteren gekookt';
    }

    if (days < 14) {
        return `${days}d geleden`;
    }

    if (days < 60) {
        return `${Math.round(days / 7)}w geleden`;
    }

    return `${Math.round(days / 30)}mnd geleden`;
}

function onDragStart(recipe: RecipeSummary): void {
    document.body.dataset.draggingRecipeId = String(recipe.id);
    document.body.classList.add('is-dragging-recipe');
}

function onDragEnd(): void {
    delete document.body.dataset.draggingRecipeId;
    document.body.classList.remove('is-dragging-recipe');
}
</script>

<template>
    <Head title="CookBook" />

    <div class="flex flex-col gap-5 p-4 md:gap-6 md:p-6">
        <div class="rounded-3xl bg-ink p-6 text-cream md:p-8">
            <div class="flex flex-wrap items-end justify-between gap-4">
                <div>
                    <p
                        class="text-[11px] font-semibold tracking-[0.22em] text-cream/60 uppercase"
                    >
                        Welkom terug
                    </p>
                    <h1
                        class="font-display mt-1 text-4xl leading-[1.05] tracking-tight md:text-5xl"
                    >
                        Hallo,
                        <span class="text-brand italic">{{ firstName }}!</span>
                    </h1>
                </div>
                <DropdownMenu>
                    <DropdownMenuTrigger as-child>
                        <button
                            type="button"
                            class="inline-flex items-center gap-2 rounded-full bg-brand px-5 py-2.5 text-sm font-medium text-ink transition hover:bg-[#d35a31] active:scale-[0.98]"
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
                                    <span class="text-xs text-muted-foreground"
                                        >Eigen recept typen</span
                                    >
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
                <span
                    class="rounded-full bg-ink/5 px-2 py-0.5 text-xs text-ink-soft tabular-nums"
                >
                    {{ recipes.data.length }}
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
                    <Star
                        class="size-3.5"
                        :fill="starredOnly ? 'currentColor' : 'none'"
                    />
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
                    type="button"
                    :class="[
                        'inline-flex items-center gap-1.5 rounded-full border px-3.5 py-1.5 text-xs font-semibold transition',
                        tagsOpen || selectedTagIds.length > 0
                            ? 'border-ink bg-ink text-cream'
                            : 'border-rule bg-cream-soft text-ink-soft hover:bg-ink/5',
                    ]"
                    @click="tagsOpen = !tagsOpen"
                >
                    <SlidersHorizontal class="size-3.5" />
                    Categorieën
                    <span
                        v-if="selectedTagIds.length > 0"
                        class="rounded-full bg-cream/20 px-1.5 text-[10px] tabular-nums"
                    >
                        {{ selectedTagIds.length }}
                    </span>
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

            <div
                v-if="selectedTags.length > 0 && !tagsOpen"
                class="flex flex-wrap items-center gap-1.5"
            >
                <span
                    class="text-[11px] font-semibold tracking-[0.18em] text-ink-faint uppercase"
                >
                    actief:
                </span>
                <button
                    v-for="tag in selectedTags"
                    :key="tag.id"
                    type="button"
                    :class="[
                        'inline-flex items-center gap-1 rounded-full border px-2.5 py-1 text-xs font-semibold transition active:scale-[0.97]',
                        tagPillClass[tag.color] ?? tagPillClass.cream,
                    ]"
                    @click="removeTag(tag.id)"
                >
                    {{ tag.name }}
                    <X class="size-3" />
                </button>
            </div>

            <div
                v-if="tagsOpen"
                class="rounded-2xl border border-rule bg-cream-soft p-4"
            >
                <div class="mb-3 flex items-center justify-between gap-2">
                    <div class="flex items-center gap-2 text-sm text-ink-soft">
                        <TagIcon class="size-4" />
                        <span>Filter op categorieën</span>
                    </div>
                    <button
                        v-if="selectedTagIds.length > 0"
                        type="button"
                        class="text-xs text-ink-soft transition hover:text-ink"
                        @click="selectedTagIds = []"
                    >
                        wis selectie
                    </button>
                </div>
                <TagChipSelect
                    v-model="selectedTagIds"
                    :tags="props.tags"
                    mode="filter"
                    :allow-create="false"
                />
            </div>
        </div>

        <div
            v-if="recipes.data.length === 0"
            class="rounded-3xl border border-dashed border-rule bg-cream-soft p-12 text-center"
        >
            <div
                class="mx-auto mb-4 flex size-12 items-center justify-center rounded-full bg-ink text-cream"
            >
                <ChefHat class="size-5" />
            </div>
            <h2 class="font-display text-2xl">
                {{
                    hasActiveFilter
                        ? 'Niets gevonden.'
                        : 'Je CookBook is nog leeg.'
                }}
            </h2>
            <p class="mt-2 text-sm text-ink-soft">
                {{
                    hasActiveFilter
                        ? 'Pas je filters aan of zoek op een ander woord.'
                        : 'Klik op "Recept toevoegen" en plak een link, een caption of begin handmatig.'
                }}
            </p>
        </div>

        <InfiniteScroll v-else data="recipes" items-element="#recipes-grid">
            <template #default>
                <div
                    id="recipes-grid"
                    class="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4"
                >
                    <RecipeCard
                        v-for="(recipe, idx) in recipes.data"
                        :key="recipe.id"
                        :recipe="recipe"
                        :index="idx"
                        :draggable="true"
                        :last-cooked-label="lastCookedLabel(recipe)"
                        @dragstart="onDragStart"
                        @dragend="onDragEnd"
                    />
                </div>
            </template>

            <template #loading>
                <div class="flex justify-center py-8 text-ink-faint">
                    <Loader2 class="size-5 animate-spin" />
                </div>
            </template>
        </InfiniteScroll>

        <ImportUrlDialog v-model:open="urlOpen" />
        <PasteRecipeDialog v-model:open="pasteOpen" />
    </div>
</template>
