<script setup lang="ts">
import { Head, Link, usePage } from '@inertiajs/vue3';
import {
    ChefHat,
    ChevronDown,
    ClipboardPaste,
    Clock,
    Link2,
    PencilLine,
    Plus,
    Search,
    Users,
} from 'lucide-vue-next';
import { computed, ref } from 'vue';
import ImportUrlDialog from '@/components/ImportUrlDialog.vue';
import PasteRecipeDialog from '@/components/PasteRecipeDialog.vue';
import {
    DropdownMenu,
    DropdownMenuContent,
    DropdownMenuItem,
    DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu';
import { dashboard } from '@/routes';
import { create as createRecipe, show as showRecipe } from '@/routes/recipes';
import type { RecipeSummary } from '@/types/recipes';

const props = defineProps<{ recipes: RecipeSummary[] }>();

defineOptions({
    layout: { breadcrumbs: [{ title: 'Recepten', href: dashboard() }] },
});

const page = usePage();
const userName = computed<string>(() => page.props.auth?.user?.name ?? 'kok');
const firstName = computed<string>(() => userName.value.split(' ')[0] ?? userName.value);

const urlOpen = ref<boolean>(false);
const pasteOpen = ref<boolean>(false);
const search = ref<string>('');

const filtered = computed<RecipeSummary[]>(() => {
    const q = search.value.trim().toLowerCase();
    if (!q) {
        return props.recipes;
    }
    return props.recipes.filter((r) => r.title.toLowerCase().includes(q));
});

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

            <div class="mt-6 grid gap-3 sm:grid-cols-3">
                <div class="rounded-2xl bg-cream/10 p-4">
                    <p class="text-[10px] font-semibold uppercase tracking-[0.2em] text-cream/55">
                        Recepten
                    </p>
                    <p class="mt-2 font-display text-3xl tabular-nums">
                        {{ recipes.length }}
                    </p>
                </div>
                <div class="rounded-2xl bg-block-lime p-4 text-ink">
                    <p class="text-[10px] font-semibold uppercase tracking-[0.2em] text-ink/60">
                        Klaar om te koken
                    </p>
                    <p class="mt-2 font-display text-3xl tabular-nums">
                        {{ recipes.length }}
                    </p>
                </div>
                <div class="rounded-2xl bg-brand p-4 text-ink">
                    <p class="text-[10px] font-semibold uppercase tracking-[0.2em] text-ink/65">
                        Laatste import
                    </p>
                    <p class="mt-2 font-display text-3xl">
                        <span v-if="recipes.length > 0">vers</span>
                        <span v-else class="italic">nog niets</span>
                    </p>
                </div>
            </div>
        </div>

        <label
            class="flex items-center gap-3 rounded-full border border-rule bg-cream-soft px-5 py-3 transition focus-within:border-brand focus-within:ring-2 focus-within:ring-brand/30"
        >
            <Search class="size-4 text-ink-faint" />
            <input
                v-model="search"
                type="search"
                placeholder="Zoek in je kookboek..."
                class="flex-1 bg-transparent text-sm outline-none placeholder:text-ink-faint"
            />
            <span class="rounded-full bg-ink/5 px-2 py-0.5 text-xs tabular-nums text-ink-soft">
                {{ filtered.length }}
            </span>
        </label>

        <div
            v-if="recipes.length === 0"
            class="rounded-3xl border border-dashed border-rule bg-cream-soft p-12 text-center"
        >
            <div class="mx-auto mb-4 flex size-12 items-center justify-center rounded-full bg-ink text-cream">
                <ChefHat class="size-5" />
            </div>
            <h2 class="font-display text-2xl">Je kookboek is nog leeg.</h2>
            <p class="mt-2 text-sm text-ink-soft">
                Klik op "Recept toevoegen" en plak een link, een caption of begin handmatig.
            </p>
        </div>

        <div
            v-else
            class="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4"
        >
            <Link
                v-for="(recipe, idx) in filtered"
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
                    <div class="absolute left-3 top-3 flex gap-1.5">
                        <span
                            v-if="recipe.cook_time_minutes"
                            class="flex items-center gap-1 rounded-full bg-cream-soft/95 px-2.5 py-1 text-[11px] font-semibold tabular-nums backdrop-blur"
                        >
                            <Clock class="size-3" /> {{ recipe.cook_time_minutes }}m
                        </span>
                        <span
                            class="flex items-center gap-1 rounded-full bg-cream-soft/95 px-2.5 py-1 text-[11px] font-semibold tabular-nums backdrop-blur"
                        >
                            <Users class="size-3" /> {{ recipe.servings }}
                        </span>
                    </div>
                </div>
                <div :class="['flex flex-1 items-end gap-3 p-5', tileBgClass[tileColor(idx)]]">
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
            </Link>
        </div>

        <ImportUrlDialog v-model:open="urlOpen" />
        <PasteRecipeDialog v-model:open="pasteOpen" />
    </div>
</template>
