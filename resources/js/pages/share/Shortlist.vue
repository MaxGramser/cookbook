<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import { ChefHat, Clock, NotebookPen, ShieldCheck, Users } from 'lucide-vue-next';
import { computed } from 'vue';
import type { ShortlistRecipe } from '@/types/recipes';

const props = defineProps<{
    token: string;
    expiresAt: string | null;
    shortlist: {
        id: number;
        name: string;
        color: string | null;
        recipes: ShortlistRecipe[];
    };
}>();

const expiryLabel = computed<string>(() => {
    if (!props.expiresAt) {
        return '';
    }

    const expires = new Date(props.expiresAt);
    const diffMs = expires.getTime() - Date.now();

    if (diffMs <= 0) {
        return 'verlopen';
    }

    const days = Math.floor(diffMs / (1000 * 60 * 60 * 24));

    if (days >= 1) {
        return `verloopt over ${days} ${days === 1 ? 'dag' : 'dagen'}`;
    }

    const hours = Math.round(diffMs / (1000 * 60 * 60));

    if (hours >= 1) {
        return `verloopt over ${hours}u`;
    }

    const minutes = Math.max(1, Math.round(diffMs / (1000 * 60)));

    return `verloopt over ${minutes} min`;
});

const heroBg: Record<string, string> = {
    lime: 'bg-block-lime text-ink',
    pink: 'bg-block-pink text-ink',
    sky: 'bg-block-sky text-ink',
    cream: 'bg-cream-soft text-ink',
    accent: 'bg-brand text-ink',
    ink: 'bg-ink text-cream',
};

const isDarkHero = computed<boolean>(() => props.shortlist.color === 'ink');

function recipeUrl(recipeId: number): string {
    return `/share/${props.token}/recipes/${recipeId}`;
}
</script>

<template>
    <Head :title="`${shortlist.name} — gedeeld`" />

    <div class="min-h-svh bg-cream font-sans text-ink">
        <header class="border-b border-rule/40 bg-cream-soft/70">
            <div
                class="mx-auto flex max-w-5xl items-center justify-between gap-3 px-4 py-3 md:px-6"
            >
                <span
                    class="inline-flex items-center gap-2 text-[11px] font-semibold tracking-[0.22em] text-ink-faint uppercase"
                >
                    <ShieldCheck class="size-3.5" />
                    Gedeelde shortlist
                </span>
                <span
                    v-if="expiryLabel"
                    class="rounded-full bg-ink/5 px-3 py-1 text-[11px] font-semibold tabular-nums text-ink-soft"
                >
                    {{ expiryLabel }}
                </span>
            </div>
        </header>

        <main class="mx-auto max-w-5xl px-4 py-6 md:px-6 md:py-10">
            <div
                :class="[
                    'rounded-3xl p-6 md:p-10',
                    heroBg[shortlist.color ?? 'cream'],
                ]"
            >
                <p
                    :class="[
                        'text-[11px] font-semibold tracking-[0.22em] uppercase',
                        isDarkHero ? 'text-cream/60' : 'text-ink/60',
                    ]"
                >
                    Shortlist
                </p>
                <h1
                    class="font-display mt-1 text-4xl leading-[1.05] tracking-tight md:text-6xl"
                >
                    {{ shortlist.name }}
                </h1>
                <p
                    :class="[
                        'mt-3 text-sm md:text-base',
                        isDarkHero ? 'text-cream/75' : 'text-ink/70',
                    ]"
                >
                    {{ shortlist.recipes.length }}
                    {{ shortlist.recipes.length === 1 ? 'recept' : 'recepten' }}
                    om samen te koken.
                </p>
            </div>

            <div
                v-if="shortlist.recipes.length === 0"
                class="mt-6 rounded-3xl border border-dashed border-rule bg-cream-soft p-12 text-center text-sm text-ink-soft"
            >
                Deze shortlist is nog leeg.
            </div>

            <div
                v-else
                class="mt-6 grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-3"
            >
                <Link
                    v-for="recipe in shortlist.recipes"
                    :key="recipe.id"
                    :href="recipeUrl(recipe.id)"
                    class="group hover:shadow-tile-hover flex flex-col overflow-hidden rounded-3xl bg-cream-soft transition hover:-translate-y-1"
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
                        <div
                            class="absolute top-3 left-3 flex flex-wrap gap-1.5"
                        >
                            <span
                                v-if="recipe.cook_time_minutes"
                                class="flex items-center gap-1 rounded-full bg-cream-soft/95 px-2.5 py-1 text-[11px] font-semibold tabular-nums backdrop-blur"
                            >
                                <Clock class="size-3" />
                                {{ recipe.cook_time_minutes }}m
                            </span>
                            <span
                                class="flex items-center gap-1 rounded-full bg-ink/85 px-2.5 py-1 text-[11px] font-semibold text-cream tabular-nums backdrop-blur"
                            >
                                <Users class="size-3" />
                                {{ recipe.servings }}
                            </span>
                        </div>
                    </div>
                    <div class="flex flex-1 flex-col gap-2 p-5">
                        <h3
                            class="font-display line-clamp-2 text-lg leading-tight"
                        >
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
                        </div>
                        <p
                            v-if="recipe.pivot.note"
                            class="mt-1 flex items-start gap-1.5 rounded-xl bg-block-pink/60 px-3 py-2 text-xs leading-relaxed text-ink/85"
                        >
                            <NotebookPen
                                class="mt-0.5 size-3.5 shrink-0 text-ink/60"
                            />
                            <span>{{ recipe.pivot.note }}</span>
                        </p>
                    </div>
                </Link>
            </div>
        </main>

        <footer
            class="mx-auto max-w-5xl px-4 pt-4 pb-10 text-center text-[11px] tracking-[0.18em] text-ink-faint uppercase md:px-6"
        >
            Read-only · gedeeld via Mijn kookboek
        </footer>
    </div>
</template>
