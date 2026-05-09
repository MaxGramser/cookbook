<script setup lang="ts">
import { Head, Link, router } from '@inertiajs/vue3';
import {
    ArrowLeft,
    ArrowRight,
    Bike,
    Check,
    ChefHat,
    Home,
    ShoppingBasket,
    Trash2,
} from 'lucide-vue-next';
import { computed, ref } from 'vue';
import GrocerySessionController from '@/actions/App/Http/Controllers/GrocerySessionController';
import { groupBySection } from '@/lib/sections';
import { formatQuantity } from '@/lib/units';
import { index as recipesIndex, show as showRecipe } from '@/routes/recipes';
import { show as showShortlist } from '@/routes/shortlists';
import type { GrocerySessionDetail, RecipeIngredient } from '@/types/recipes';

const props = defineProps<{
    session: GrocerySessionDetail;
}>();

type CheckPhase = 'home' | 'shopping';

const checkedIngredients = ref<Map<number, CheckPhase>>(
    new Map(props.session.checks.map((c) => [c.id, c.phase])),
);

const phase = computed<CheckPhase>(() => props.session.phase);
const isShopping = computed<boolean>(() => phase.value === 'shopping');

const isShortlist = computed<boolean>(
    () => props.session.subject_type === 'shortlist',
);

type RecipeBlock = {
    recipe: GrocerySessionDetail['recipes'][number];
    groups: ReturnType<typeof groupBySection<RecipeIngredient>>;
};

const recipeBlocks = computed<RecipeBlock[]>(() =>
    props.session.recipes.map((recipe) => ({
        recipe,
        groups: groupBySection(recipe.ingredients),
    })),
);

const totalCount = computed<number>(() =>
    props.session.recipes.reduce((acc, r) => acc + r.ingredients.length, 0),
);
const checkedCount = computed<number>(() => checkedIngredients.value.size);
const remainingCount = computed<number>(
    () => totalCount.value - checkedCount.value,
);

const homePct = computed<number>(() => {
    if (totalCount.value === 0) {
        return 0;
    }

    return Math.round((checkedCount.value / totalCount.value) * 100);
});

const shoppingPct = computed<number>(() => {
    if (totalCount.value === 0) {
        return 0;
    }

    return Math.round((checkedCount.value / totalCount.value) * 100);
});

const backHref = computed<string>(() =>
    isShortlist.value
        ? showShortlist(props.session.subject.id).url
        : showRecipe(props.session.subject.id).url,
);

function isChecked(ingredient: RecipeIngredient): boolean {
    return checkedIngredients.value.has(ingredient.id);
}

function checkPhase(ingredient: RecipeIngredient): CheckPhase | null {
    return checkedIngredients.value.get(ingredient.id) ?? null;
}

function toggleIngredient(ingredient: RecipeIngredient): void {
    const next = !isChecked(ingredient);

    if (next) {
        checkedIngredients.value.set(ingredient.id, phase.value);
    } else {
        checkedIngredients.value.delete(ingredient.id);
    }

    checkedIngredients.value = new Map(checkedIngredients.value);

    router.post(
        GrocerySessionController.toggleIngredient.url({
            session: props.session.id,
            ingredient: ingredient.id,
        }),
        { checked: next },
        { preserveScroll: true, preserveState: true, only: [] },
    );
}

function blockCheckedCount(block: RecipeBlock): number {
    return block.recipe.ingredients.filter((i) =>
        checkedIngredients.value.has(i.id),
    ).length;
}

function advanceToShopping(): void {
    router.post(
        GrocerySessionController.phase.url(props.session.id),
        { phase: 'shopping' },
        { preserveScroll: false },
    );
}

function backToHome(): void {
    router.post(
        GrocerySessionController.phase.url(props.session.id),
        { phase: 'home' },
        { preserveScroll: false },
    );
}

function complete(): void {
    router.post(GrocerySessionController.complete.url(props.session.id));
}

function confirmCancel(): void {
    if (!window.confirm('Boodschappensessie afbreken?')) {
        return;
    }

    router.delete(GrocerySessionController.destroy.url(props.session.id));
}

defineOptions({
    layout: { breadcrumbs: [{ title: 'Recepten', href: recipesIndex() }] },
});
</script>

<template>
    <Head :title="`Boodschappen — ${session.subject.title}`" />

    <div class="min-h-svh bg-cream pb-32 text-ink">
        <header
            class="sticky top-0 z-30 border-b border-rule/40 bg-cream/95 backdrop-blur"
        >
            <div
                class="mx-auto flex max-w-2xl items-center justify-between gap-3 px-4 py-3"
            >
                <Link
                    :href="backHref"
                    class="inline-flex items-center gap-2 text-sm text-ink-soft transition hover:text-ink"
                >
                    <ArrowLeft class="size-4" />
                    <span
                        class="font-display line-clamp-1 max-w-[12rem] text-lg leading-tight"
                    >
                        {{ session.subject.title }}
                    </span>
                </Link>
                <button
                    type="button"
                    class="inline-flex items-center gap-1.5 rounded-full border border-warn/30 px-3 py-1.5 text-xs font-medium text-warn transition hover:bg-warn/5"
                    @click="confirmCancel"
                >
                    <Trash2 class="size-3.5" /> Afbreken
                </button>
            </div>
            <div class="mx-auto flex max-w-2xl items-center gap-2 px-4 pb-3">
                <div
                    :class="[
                        'flex items-center gap-1.5 rounded-full px-3 py-1 text-[11px] font-semibold tracking-[0.2em] uppercase transition',
                        !isShopping
                            ? 'bg-block-sky text-ink'
                            : 'bg-cream-soft text-ink-faint',
                    ]"
                >
                    <Home class="size-3.5" /> 1. Thuis
                </div>
                <span class="h-[2px] flex-1 bg-rule"></span>
                <div
                    :class="[
                        'flex items-center gap-1.5 rounded-full px-3 py-1 text-[11px] font-semibold tracking-[0.2em] uppercase transition',
                        isShopping
                            ? 'bg-block-lime text-ink'
                            : 'bg-cream-soft text-ink-faint',
                    ]"
                >
                    <ShoppingBasket class="size-3.5" /> 2. Winkel
                </div>
            </div>
        </header>

        <main class="mx-auto max-w-2xl px-4 pt-5">
            <section
                v-if="!isShopping"
                class="rounded-3xl bg-block-sky p-5 md:p-6"
            >
                <div class="flex items-start gap-3">
                    <span
                        class="grid size-10 shrink-0 place-items-center rounded-full bg-ink text-cream"
                    >
                        <Home class="size-5" />
                    </span>
                    <div>
                        <h1
                            class="font-display text-2xl leading-tight md:text-3xl"
                        >
                            Welke ingrediënten heb je nog thuis?
                        </h1>
                        <p class="mt-1 text-sm leading-relaxed text-ink/80">
                            Streep af wat je al hebt staan. De rest neem je
                            straks mee uit de winkel.
                        </p>
                    </div>
                </div>
                <div class="mt-4 flex items-center gap-2">
                    <div
                        class="h-2 flex-1 overflow-hidden rounded-full bg-ink/10"
                    >
                        <div
                            class="h-full rounded-full bg-ink transition-all duration-300"
                            :style="{ width: `${homePct}%` }"
                        ></div>
                    </div>
                    <span
                        class="font-mono text-xs font-semibold text-ink/80 tabular-nums"
                    >
                        {{ checkedCount }}/{{ totalCount }}
                    </span>
                </div>
            </section>

            <section v-else class="rounded-3xl bg-block-lime p-5 md:p-6">
                <div class="flex items-start gap-3">
                    <span
                        class="grid size-10 shrink-0 place-items-center rounded-full bg-ink text-cream"
                    >
                        <ShoppingBasket class="size-5" />
                    </span>
                    <div>
                        <h1
                            class="font-display text-2xl leading-tight md:text-3xl"
                        >
                            <span class="inline-flex items-center gap-2">
                                <Bike class="size-6" /> Onderweg & in de winkel
                            </span>
                        </h1>
                        <p class="mt-1 text-sm leading-relaxed text-ink/85">
                            Streep af zodra je iets in je mandje legt.
                            <span v-if="remainingCount > 0"
                                >Nog {{ remainingCount }} te halen.</span
                            >
                            <span v-else>Alles is binnen!</span>
                        </p>
                    </div>
                </div>
                <div class="mt-4 flex items-center gap-2">
                    <div
                        class="h-2 flex-1 overflow-hidden rounded-full bg-ink/10"
                    >
                        <div
                            class="h-full rounded-full bg-ink transition-all duration-300"
                            :style="{ width: `${shoppingPct}%` }"
                        ></div>
                    </div>
                    <span
                        class="font-mono text-xs font-semibold text-ink/80 tabular-nums"
                    >
                        {{ checkedCount }}/{{ totalCount }}
                    </span>
                </div>
            </section>

            <div class="mt-5 flex flex-col gap-5">
                <section
                    v-for="block in recipeBlocks"
                    :key="block.recipe.id"
                    class="rounded-3xl bg-cream-soft p-5 md:p-6"
                >
                    <div class="mb-4 flex items-baseline justify-between gap-3">
                        <div class="min-w-0">
                            <p
                                v-if="isShortlist"
                                class="text-[11px] font-semibold tracking-[0.2em] text-ink-faint uppercase"
                            >
                                Gerecht
                            </p>
                            <h2 class="font-display text-xl leading-tight">
                                {{
                                    isShortlist
                                        ? block.recipe.title
                                        : isShopping
                                          ? 'Boodschappenlijst'
                                          : 'Ingrediënten'
                                }}
                            </h2>
                        </div>
                        <span
                            class="shrink-0 text-[11px] font-semibold tracking-[0.18em] text-ink-faint uppercase"
                        >
                            {{ blockCheckedCount(block) }}/{{
                                block.recipe.ingredients.length
                            }}
                        </span>
                    </div>

                    <div class="flex flex-col gap-4">
                        <div
                            v-for="(group, groupIdx) in block.groups"
                            :key="`g-${block.recipe.id}-${groupIdx}`"
                            class="flex flex-col gap-1.5"
                        >
                            <h3
                                v-if="group.section"
                                class="px-1 pt-1 text-[11px] font-semibold tracking-[0.2em] text-ink-faint uppercase"
                            >
                                {{ group.section }}
                            </h3>
                            <ul class="flex flex-col gap-1.5">
                                <li
                                    v-for="ingredient in group.items"
                                    :key="ingredient.id"
                                >
                                    <button
                                        type="button"
                                        :class="[
                                            'flex w-full items-center gap-3 rounded-xl border px-3 py-3 text-left text-sm transition active:scale-[0.99]',
                                            checkPhase(ingredient) === 'home'
                                                ? 'border-transparent bg-block-sky/70 text-ink/75'
                                                : checkPhase(ingredient) ===
                                                    'shopping'
                                                  ? 'border-transparent bg-block-lime/80 text-ink/75'
                                                  : 'border-rule bg-cream text-ink hover:bg-block-cream',
                                        ]"
                                        @click="toggleIngredient(ingredient)"
                                    >
                                        <span
                                            :class="[
                                                'grid size-7 shrink-0 place-items-center rounded-full border-2 transition',
                                                isChecked(ingredient)
                                                    ? 'border-ink bg-ink text-cream'
                                                    : 'border-ink/30 bg-cream',
                                            ]"
                                        >
                                            <Check
                                                v-if="isChecked(ingredient)"
                                                class="size-4"
                                            />
                                        </span>
                                        <span
                                            :class="[
                                                'flex-1 leading-snug',
                                                isChecked(ingredient) &&
                                                    'line-through',
                                            ]"
                                        >
                                            {{ ingredient.name }}
                                        </span>
                                        <Home
                                            v-if="
                                                checkPhase(ingredient) ===
                                                'home'
                                            "
                                            class="size-3.5 shrink-0 text-ink/55"
                                        />
                                        <ShoppingBasket
                                            v-else-if="
                                                checkPhase(ingredient) ===
                                                'shopping'
                                            "
                                            class="size-3.5 shrink-0 text-ink/55"
                                        />
                                        <span
                                            :class="[
                                                'shrink-0 font-semibold tabular-nums',
                                                isChecked(ingredient)
                                                    ? 'text-ink/65'
                                                    : 'text-ink-soft',
                                            ]"
                                        >
                                            {{
                                                formatQuantity(
                                                    ingredient.quantity,
                                                    ingredient.unit,
                                                ) || ingredient.raw_text
                                            }}
                                        </span>
                                    </button>
                                </li>
                            </ul>
                        </div>
                    </div>
                </section>
            </div>

            <p
                v-if="
                    !isShopping && checkedCount === totalCount && totalCount > 0
                "
                class="mt-4 rounded-2xl bg-block-pink p-4 text-center text-sm leading-relaxed text-ink"
            >
                Alles thuis? Mooi — je hoeft niks te halen. Je kan meteen door
                naar koken.
            </p>
        </main>

        <div
            class="fixed inset-x-0 bottom-0 z-40 border-t border-rule/40 bg-cream/95 backdrop-blur"
        >
            <div class="mx-auto flex max-w-2xl items-center gap-2 px-4 py-3">
                <template v-if="!isShopping">
                    <button
                        type="button"
                        class="inline-flex flex-1 items-center justify-center gap-2 rounded-full bg-ink px-5 py-3 text-sm font-semibold text-cream transition hover:bg-[#3A2C24] active:scale-[0.98]"
                        @click="advanceToShopping"
                    >
                        <Bike class="size-4" />
                        <span v-if="remainingCount === 0"
                            >Alles thuis — door naar winkel</span
                        >
                        <span v-else>
                            Op naar de winkel
                            <span
                                class="ml-1 font-mono tabular-nums opacity-80"
                            >
                                ({{ remainingCount }} te halen)
                            </span>
                        </span>
                        <ArrowRight class="size-4" />
                    </button>
                </template>
                <template v-else>
                    <button
                        type="button"
                        class="inline-flex items-center justify-center gap-2 rounded-full border border-rule bg-cream-soft px-4 py-3 text-sm font-medium text-ink-soft transition hover:bg-block-cream"
                        @click="backToHome"
                    >
                        <ArrowLeft class="size-4" /> Terug
                    </button>
                    <button
                        type="button"
                        :disabled="totalCount > 0 && checkedCount < totalCount"
                        :class="[
                            'inline-flex flex-1 items-center justify-center gap-2 rounded-full px-5 py-3 text-sm font-semibold transition active:scale-[0.98]',
                            totalCount === 0 || checkedCount === totalCount
                                ? 'bg-brand text-ink hover:bg-[#d35a31]'
                                : 'bg-ink/10 text-ink/40',
                        ]"
                        @click="complete"
                    >
                        <ChefHat class="size-4" /> Klaar om te koken!
                    </button>
                </template>
            </div>
        </div>
    </div>
</template>
