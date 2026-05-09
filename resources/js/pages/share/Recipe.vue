<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import {
    ArrowLeft,
    ChefHat,
    Clock,
    ExternalLink,
    NotebookPen,
    Printer,
    ShieldCheck,
    Users,
} from 'lucide-vue-next';
import { computed, ref } from 'vue';
import PrintRecipeDialog from '@/components/PrintRecipeDialog.vue';
import { groupBySection } from '@/lib/sections';
import { formatQuantity } from '@/lib/units';
import type { Recipe, Tag, TagColor } from '@/types/recipes';

const props = defineProps<{
    token: string;
    expiresAt: string | null;
    shortlist: { id: number; name: string; color: string | null };
    note: string | null;
    recipe: Recipe;
}>();

const ingredientGroups = computed(() => groupBySection(props.recipe.ingredients));
const stepGroups = computed(() => groupBySection(props.recipe.steps));
const printOpen = ref<boolean>(false);

const tagColorClass: Record<TagColor, string> = {
    cream: 'bg-cream text-ink border-rule',
    lime: 'bg-block-lime text-ink border-transparent',
    pink: 'bg-block-pink text-ink border-transparent',
    sky: 'bg-block-sky text-ink border-transparent',
    accent: 'bg-brand text-ink border-transparent',
    ink: 'bg-ink text-cream border-transparent',
};

const expiryLabel = computed<string>(() => {
    if (!props.expiresAt) {
        return '';
    }

    const expires = new Date(props.expiresAt);
    const diffMs = expires.getTime() - Date.now();

    if (diffMs <= 0) {
        return 'verlopen';
    }

    const hours = Math.round(diffMs / (1000 * 60 * 60));

    if (hours < 1) {
        const minutes = Math.max(1, Math.round(diffMs / (1000 * 60)));

        return `verloopt over ${minutes} min`;
    }

    return `verloopt over ${hours}u`;
});
</script>

<template>
    <Head :title="`${recipe.title} — gedeeld`" />

    <div class="min-h-svh bg-cream font-sans text-ink">
        <header class="border-b border-rule/40 bg-cream-soft/70">
            <div
                class="mx-auto flex max-w-5xl items-center justify-between gap-3 px-4 py-3 md:px-6"
            >
                <Link
                    :href="`/share/${token}`"
                    class="inline-flex items-center gap-2 text-sm text-ink-soft transition hover:text-ink"
                >
                    <ArrowLeft class="size-4" />
                    <span class="line-clamp-1 max-w-[14rem]">
                        {{ shortlist.name }}
                    </span>
                </Link>
                <span
                    class="inline-flex items-center gap-2 text-[11px] font-semibold tracking-[0.22em] text-ink-faint uppercase"
                >
                    <ShieldCheck class="size-3.5" />
                    Gedeeld
                </span>
                <span
                    v-if="expiryLabel"
                    class="rounded-full bg-ink/5 px-3 py-1 text-[11px] font-semibold tabular-nums text-ink-soft"
                >
                    {{ expiryLabel }}
                </span>
            </div>
        </header>

        <main
            class="mx-auto flex max-w-5xl flex-col gap-5 px-4 py-6 md:px-6 md:py-8"
        >
            <div class="grid gap-5 md:grid-cols-[5fr_4fr]">
                <div
                    class="shadow-tile overflow-hidden rounded-3xl bg-cream-soft"
                >
                    <div class="aspect-[4/3] w-full overflow-hidden bg-ink/5">
                        <img
                            v-if="recipe.image_path"
                            :src="`/storage/${recipe.image_path}`"
                            :alt="recipe.title"
                            class="h-full w-full object-cover"
                        />
                        <div
                            v-else
                            class="flex h-full w-full items-center justify-center text-ink-faint"
                        >
                            <ChefHat class="size-12" />
                        </div>
                    </div>
                    <div class="flex flex-wrap items-center gap-2 p-5">
                        <span
                            v-if="recipe.cook_time_minutes"
                            class="flex items-center gap-1.5 rounded-full bg-ink px-3 py-1 text-xs font-semibold text-cream tabular-nums"
                        >
                            <Clock class="size-3.5" />
                            {{ recipe.cook_time_minutes }} min
                        </span>
                        <span
                            class="flex items-center gap-1.5 rounded-full bg-block-lime px-3 py-1 text-xs font-semibold text-ink tabular-nums"
                        >
                            <Users class="size-3.5" />
                            {{ recipe.servings }} pers
                        </span>
                        <a
                            v-if="recipe.source_url"
                            :href="recipe.source_url"
                            target="_blank"
                            rel="noopener"
                            class="flex items-center gap-1.5 rounded-full border border-rule px-3 py-1 text-xs text-ink-soft transition hover:bg-ink/5"
                        >
                            <ExternalLink class="size-3.5" /> bron
                        </a>
                        <span
                            v-for="tag in recipe.tags ?? []"
                            :key="tag.id"
                            :class="[
                                'rounded-full border px-3 py-1 text-xs font-semibold',
                                tagColorClass[tag.color] ??
                                    tagColorClass.cream,
                            ]"
                        >
                            {{ tag.name }}
                        </span>
                    </div>
                </div>

                <div
                    class="flex flex-col justify-between gap-5 rounded-3xl bg-ink p-6 text-cream md:p-8"
                >
                    <div>
                        <p
                            class="text-[11px] font-semibold tracking-[0.22em] text-cream/55 uppercase"
                        >
                            Recept
                        </p>
                        <h1
                            class="font-display mt-2 text-4xl leading-[1.05] tracking-tight md:text-5xl"
                        >
                            {{ recipe.title }}
                        </h1>
                    </div>
                    <div class="flex flex-wrap gap-2">
                        <button
                            type="button"
                            class="inline-flex items-center gap-2 rounded-full bg-brand px-5 py-2.5 text-sm font-medium text-ink transition hover:bg-[#d35a31] active:scale-[0.98]"
                            @click="printOpen = true"
                        >
                            <Printer class="size-4" /> Print
                        </button>
                    </div>
                </div>
            </div>

            <section
                v-if="note"
                class="rounded-3xl bg-block-pink p-5 md:p-6"
            >
                <div class="flex items-start gap-3">
                    <span
                        class="grid size-9 shrink-0 place-items-center rounded-full bg-ink text-cream"
                    >
                        <NotebookPen class="size-4" />
                    </span>
                    <div>
                        <h2
                            class="text-[11px] font-semibold tracking-[0.2em] text-ink/60 uppercase"
                        >
                            Notitie van de delende kok
                        </h2>
                        <p
                            class="mt-1 text-sm leading-relaxed whitespace-pre-line text-ink/85"
                        >
                            {{ note }}
                        </p>
                    </div>
                </div>
            </section>

            <div class="grid gap-5 md:grid-cols-[3fr_5fr]">
                <section class="rounded-3xl bg-cream-soft p-5 md:p-6">
                    <div class="mb-4 flex items-baseline justify-between">
                        <h2 class="font-display text-2xl leading-tight">
                            Ingrediënten
                        </h2>
                        <span
                            class="text-[11px] font-semibold tracking-[0.18em] text-ink-faint uppercase"
                        >
                            {{ recipe.ingredients.length }} regels
                        </span>
                    </div>
                    <div class="flex flex-col gap-4">
                        <div
                            v-for="(group, groupIdx) in ingredientGroups"
                            :key="`ig-${groupIdx}`"
                            class="flex flex-col gap-1"
                        >
                            <h3
                                v-if="group.section"
                                class="px-1 pt-1 text-[11px] font-semibold tracking-[0.2em] text-ink-faint uppercase"
                            >
                                {{ group.section }}
                            </h3>
                            <ul class="flex flex-col gap-1">
                                <li
                                    v-for="ingredient in group.items"
                                    :key="ingredient.id"
                                    class="flex items-center justify-between gap-3 rounded-xl border border-rule bg-cream px-4 py-2.5 text-sm"
                                >
                                    <span class="flex-1">{{
                                        ingredient.name
                                    }}</span>
                                    <span
                                        class="shrink-0 font-semibold text-ink-soft tabular-nums"
                                    >
                                        {{
                                            formatQuantity(
                                                ingredient.quantity,
                                                ingredient.unit,
                                            ) || ingredient.raw_text
                                        }}
                                    </span>
                                </li>
                            </ul>
                        </div>
                    </div>
                </section>

                <section class="rounded-3xl bg-cream-soft p-5 md:p-6">
                    <div class="mb-4 flex items-baseline justify-between">
                        <h2 class="font-display text-2xl leading-tight">
                            Stappen
                        </h2>
                        <span
                            class="text-[11px] font-semibold tracking-[0.18em] text-ink-faint uppercase"
                        >
                            {{ recipe.steps.length }} stappen
                        </span>
                    </div>
                    <div class="flex flex-col gap-4">
                        <div
                            v-for="(group, groupIdx) in stepGroups"
                            :key="`sg-${groupIdx}`"
                            class="flex flex-col gap-2"
                        >
                            <h3
                                v-if="group.section"
                                class="px-1 pt-1 text-[11px] font-semibold tracking-[0.2em] text-ink-faint uppercase"
                            >
                                {{ group.section }}
                            </h3>
                            <ol class="flex flex-col gap-2">
                                <li
                                    v-for="step in group.items"
                                    :key="step.id"
                                    class="flex gap-3 rounded-xl border border-rule bg-cream p-4 text-sm"
                                >
                                    <span
                                        class="grid size-8 shrink-0 place-items-center rounded-full bg-ink text-sm font-semibold text-cream"
                                    >
                                        {{ step.position }}
                                    </span>
                                    <p
                                        class="flex-1 leading-relaxed whitespace-pre-line"
                                    >
                                        {{ step.body }}
                                    </p>
                                </li>
                            </ol>
                        </div>
                    </div>
                </section>
            </div>

            <section
                v-if="recipe.notes"
                class="rounded-3xl bg-block-pink p-5 md:p-6"
            >
                <h2 class="font-display text-2xl leading-tight">
                    Notities
                </h2>
                <p
                    class="mt-2 text-sm leading-relaxed whitespace-pre-line text-ink/85"
                >
                    {{ recipe.notes }}
                </p>
            </section>
        </main>

        <footer
            class="mx-auto max-w-5xl px-4 pt-4 pb-10 text-center text-[11px] tracking-[0.18em] text-ink-faint uppercase md:px-6"
        >
            Read-only · gedeeld via Mijn kookboek
        </footer>

        <PrintRecipeDialog v-model:open="printOpen" :recipe="recipe" />
    </div>
</template>
