<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import {
    ArrowRight,
    ChefHat,
    ClipboardPaste,
    Clock,
    Languages,
    Link2,
    PencilLine,
    Printer,
    Scale,
    Timer,
} from 'lucide-vue-next';
import ActiveCookSessionPill from '@/components/ActiveCookSessionPill.vue';
import { dashboard, login, register } from '@/routes';

withDefaults(
    defineProps<{
        canRegister: boolean;
    }>(),
    { canRegister: true },
);

type ShowcaseRecipe = {
    title: string;
    meta: string;
    image: string;
    block: 'lime' | 'pink' | 'sky' | 'cream' | 'ink' | 'accent';
};

const showcase: ShowcaseRecipe[] = [
    {
        title: 'Indonesische sajoer boontjes',
        meta: '4 personen · 25 min',
        image: 'https://images.unsplash.com/photo-1604908176997-125f25cc6f3d?auto=format&fit=crop&w=900&q=70',
        block: 'lime',
    },
    {
        title: 'Kip met ingelegde citroen',
        meta: '4 personen · 55 min',
        image: 'https://images.unsplash.com/photo-1598103442097-8b74394b95c6?auto=format&fit=crop&w=900&q=70',
        block: 'accent',
    },
    {
        title: 'Knapperige tofu-shawarma wraps',
        meta: '2 personen · 30 min',
        image: 'https://images.unsplash.com/photo-1565299624946-b28f40a0ae38?auto=format&fit=crop&w=900&q=70',
        block: 'sky',
    },
    {
        title: 'Pasta carbonara',
        meta: '4 personen · 25 min',
        image: 'https://images.unsplash.com/photo-1600803907087-f56d462fd26b?auto=format&fit=crop&w=900&q=70',
        block: 'cream',
    },
    {
        title: 'Asian chilli chicken',
        meta: '4 personen · 13 min',
        image: 'https://images.unsplash.com/photo-1626804475297-41608ea09aeb?auto=format&fit=crop&w=900&q=70',
        block: 'pink',
    },
    {
        title: 'Bao buns met varkensvlees',
        meta: '20 buns · 90 min',
        image: 'https://images.unsplash.com/photo-1626571659240-1cda3f2dee1f?auto=format&fit=crop&w=900&q=70',
        block: 'ink',
    },
];

const features = [
    {
        icon: Languages,
        title: 'Vertaalt automatisch naar Nederlands',
        body: 'Plak een Engelse, Italiaanse of Franse blogpost — je krijgt een keurig Nederlands recept terug. Hoeveelheden blijven verbatim, alleen de tekst wordt vertaald.',
    },
    {
        icon: Scale,
        title: 'Cups, oz en lbs → gram & milliliter',
        body: 'Geen Amerikaans cupgepuzzel meer. Locale-aware: 1 cup wordt 237 ml in een NYT-recept, 250 ml bij RecipeTinEats. Theelepel en eetlepel blijven gewoon tl en el.',
    },
    {
        icon: ChefHat,
        title: 'Kookmodus voor je telefoon',
        body: 'Sticky timer, afvinkbare ingrediënten en stappen, scherm blijft aan. Pauzeer met één tap — ook step-timers stoppen netjes.',
    },
    {
        icon: Timer,
        title: 'Step-timers met geluid',
        body: 'Recept zegt "kook 8 minuten"? Eén tap start de timer. Drie tonen + haptic vibratie als hij klaar is. Meerdere timers tegelijk werken prima.',
    },
    {
        icon: Printer,
        title: 'Print mooi op A4',
        body: 'Schoon, serif-gestyled, twee koloms. Schaal de porties in het printvenster zonder het opgeslagen recept aan te passen.',
    },
    {
        icon: Clock,
        title: 'Kookgeschiedenis',
        body: 'Ziet wanneer je iets kookte, hoe lang het duurde, en je notities erbij. "Volgende keer minder zout" blijft bewaard.',
    },
];

const importMethods = [
    {
        icon: Link2,
        title: 'Plak een URL',
        body: 'Werkt op NYT Cooking, RecipeTinEats, 24Kitchen, Eef Kookt Zo, gewooneenfoodblog en honderden anderen.',
        block: 'sky' as const,
    },
    {
        icon: ClipboardPaste,
        title: 'Plak een caption',
        body: 'Instagram-reel? TikTok? Een mailtje van je oma? Plak de tekst, optioneel een foto erbij — klaar.',
        block: 'lime' as const,
    },
    {
        icon: PencilLine,
        title: 'Schrijf het zelf',
        body: 'Eigen recepten typen kan natuurlijk ook. Sectiekopjes, ingrediëntenlijst, stappen — net zoals je gewend bent.',
        block: 'pink' as const,
    },
];

const blockClass: Record<ShowcaseRecipe['block'], string> = {
    lime: 'bg-block-lime text-ink',
    pink: 'bg-block-pink text-ink',
    sky: 'bg-block-sky text-ink',
    cream: 'bg-cream-soft text-ink',
    ink: 'bg-ink text-cream',
    accent: 'bg-brand text-ink',
};
</script>

<template>
    <Head title="Mijn kookboek" />

    <div class="min-h-screen bg-cream text-ink">
        <nav class="sticky top-0 z-30 border-b border-rule bg-cream/85 backdrop-blur">
            <div class="mx-auto flex max-w-6xl items-center justify-between px-5 py-4">
                <div class="flex items-center gap-2 font-display text-xl italic">
                    <span class="flex size-8 items-center justify-center rounded-full bg-ink text-cream">
                        <ChefHat class="size-4" />
                    </span>
                    <span>Mijn kookboek</span>
                </div>
                <div class="flex items-center gap-2 text-sm">
                    <Link
                        v-if="$page.props.auth.user"
                        :href="dashboard()"
                        class="rounded-full bg-ink px-4 py-2 font-medium text-cream transition active:scale-[0.98] hover:bg-[#3a2c24]"
                    >
                        Naar mijn kookboek
                    </Link>
                    <template v-else>
                        <Link
                            :href="login()"
                            class="rounded-full px-4 py-2 transition hover:bg-ink/5"
                        >
                            Inloggen
                        </Link>
                        <Link
                            v-if="canRegister"
                            :href="register()"
                            class="rounded-full bg-ink px-4 py-2 font-medium text-cream transition active:scale-[0.98] hover:bg-[#3a2c24]"
                        >
                            Begin
                        </Link>
                    </template>
                </div>
            </div>
        </nav>

        <header class="mx-auto max-w-6xl px-4 pb-6 pt-10 md:px-5 md:pt-16">
            <div class="grid gap-6 lg:grid-cols-[1.05fr_0.95fr] lg:gap-10">
                <div class="flex flex-col gap-5">
                    <div class="rounded-3xl bg-block-lime p-6 md:p-8">
                        <p class="mb-4 text-xs font-semibold uppercase tracking-[0.22em] text-ink/70">
                            De allerbeste cookbook app
                        </p>
                        <h1
                            class="font-display text-5xl leading-[0.95] tracking-tight md:text-6xl lg:text-[4.5rem]"
                        >
                            Een kookboek dat
                            <span class="italic">begrijpt</span>
                            wat je kookt.
                        </h1>
                    </div>

                    <div class="rounded-3xl bg-block-sky p-6 md:p-7">
                        <p class="text-base leading-relaxed text-ink md:text-lg">
                            Plak een link, een Instagram caption of typ je eigen recept — wij
                            maken er één schoon, vertaald en geschaald recept van. Dan koken
                            we mee: timer, afvinklijst, pauzeknop. Net wat je nodig hebt aan
                            het fornuis.
                        </p>
                        <div class="mt-5 flex flex-wrap gap-2.5">
                            <Link
                                v-if="$page.props.auth.user"
                                :href="dashboard()"
                                class="group inline-flex items-center gap-2 rounded-full bg-ink px-5 py-2.5 text-sm font-medium text-cream transition active:scale-[0.98] hover:bg-[#3a2c24]"
                            >
                                Naar mijn kookboek
                                <ArrowRight class="size-4 transition group-hover:translate-x-0.5" />
                            </Link>
                            <template v-else>
                                <Link
                                    v-if="canRegister"
                                    :href="register()"
                                    class="group inline-flex items-center gap-2 rounded-full bg-ink px-5 py-2.5 text-sm font-medium text-cream transition active:scale-[0.98] hover:bg-[#3a2c24]"
                                >
                                    Maak een kookboek
                                    <ArrowRight class="size-4 transition group-hover:translate-x-0.5" />
                                </Link>
                                <Link
                                    :href="login()"
                                    class="inline-flex items-center gap-2 rounded-full border border-ink/25 px-5 py-2.5 text-sm font-medium transition hover:bg-ink/5"
                                >
                                    Al een account
                                </Link>
                            </template>
                        </div>
                    </div>

                    <div class="grid grid-cols-3 gap-3">
                        <div class="rounded-2xl bg-cream-soft p-4">
                            <p class="font-display text-3xl leading-none">∞</p>
                            <p class="mt-2 text-[11px] font-semibold uppercase tracking-[0.18em] text-ink-faint">
                                Recepten
                            </p>
                        </div>
                        <div class="rounded-2xl bg-brand p-4">
                            <p class="font-display text-3xl leading-none tabular-nums">1m</p>
                            <p class="mt-2 text-[11px] font-semibold uppercase tracking-[0.18em] text-ink/70">
                                tot je 1e import
                            </p>
                        </div>
                        <div class="rounded-2xl bg-ink p-4 text-cream">
                            <p class="font-display text-3xl leading-none tabular-nums">14</p>
                            <p class="mt-2 text-[11px] font-semibold uppercase tracking-[0.18em] text-cream/70">
                                Talen in
                            </p>
                        </div>
                    </div>
                </div>

                <div class="grid grid-cols-2 gap-3 self-center">
                    <div class="flex flex-col gap-3">
                        <div
                            class="relative aspect-[3/4] overflow-hidden rounded-3xl bg-block-pink shadow-tile"
                        >
                            <img
                                :src="showcase[0].image"
                                :alt="showcase[0].title"
                                class="h-full w-full object-cover"
                                loading="lazy"
                            />
                            <span
                                class="absolute left-3 top-3 rounded-full bg-cream-soft px-2.5 py-1 text-[11px] font-semibold uppercase tracking-[0.16em]"
                            >
                                Indonesisch
                            </span>
                        </div>
                        <div class="rounded-3xl bg-brand p-5 text-ink shadow-tile">
                            <p class="text-[11px] font-semibold uppercase tracking-[0.2em] text-ink/70">
                                Kookmodus
                            </p>
                            <p class="mt-2 font-display text-4xl tabular-nums">
                                00:08:42
                            </p>
                            <p class="mt-1 text-xs text-ink/70">Pasta · stap 4 van 7</p>
                        </div>
                    </div>
                    <div class="flex flex-col gap-3 pt-10">
                        <div class="rounded-3xl bg-ink p-5 text-cream shadow-tile">
                            <p class="text-[11px] font-semibold uppercase tracking-[0.2em] text-cream/60">
                                Vandaag
                            </p>
                            <p class="mt-2 font-display text-3xl leading-tight">
                                Welkom terug,
                                <span class="italic">kok!</span>
                            </p>
                            <p class="mt-3 text-xs text-cream/70">3 nieuwe recepten klaar</p>
                        </div>
                        <div
                            class="relative aspect-[3/4] overflow-hidden rounded-3xl bg-block-sky shadow-tile"
                        >
                            <img
                                :src="showcase[2].image"
                                :alt="showcase[2].title"
                                class="h-full w-full object-cover"
                                loading="lazy"
                            />
                            <span
                                class="absolute bottom-3 left-3 rounded-full bg-ink px-2.5 py-1 text-[11px] font-semibold uppercase tracking-[0.16em] text-cream"
                            >
                                30 min
                            </span>
                        </div>
                    </div>
                </div>
            </div>
        </header>

        <section class="mx-auto max-w-6xl px-4 pb-12 pt-12 md:px-5 md:pt-20">
            <div class="mb-8 max-w-2xl">
                <p class="mb-2 text-xs font-semibold uppercase tracking-[0.22em] text-brand">
                    Drie manieren om iets toe te voegen
                </p>
                <h2 class="font-display text-3xl tracking-tight md:text-5xl">
                    Geef ons je recept in
                    <span class="italic">welke vorm dan ook.</span>
                </h2>
            </div>
            <div class="grid gap-4 md:grid-cols-3">
                <div
                    v-for="method in importMethods"
                    :key="method.title"
                    :class="[
                        'rounded-3xl p-6 transition active:scale-[0.99]',
                        method.block === 'sky' && 'bg-block-sky',
                        method.block === 'lime' && 'bg-block-lime',
                        method.block === 'pink' && 'bg-block-pink',
                    ]"
                >
                    <div class="mb-5 flex size-11 items-center justify-center rounded-full bg-ink text-cream">
                        <component :is="method.icon" class="size-5" />
                    </div>
                    <h3 class="font-display text-2xl leading-tight">{{ method.title }}</h3>
                    <p class="mt-3 text-sm leading-relaxed text-ink/75">{{ method.body }}</p>
                </div>
            </div>
        </section>

        <section class="mx-auto max-w-6xl px-4 py-12 md:px-5 md:py-20">
            <div class="mb-8 flex flex-wrap items-end justify-between gap-4">
                <div class="max-w-xl">
                    <p class="mb-2 text-xs font-semibold uppercase tracking-[0.22em] text-brand">
                        Een klein voorproefje
                    </p>
                    <h2 class="font-display text-3xl tracking-tight md:text-5xl">
                        Recepten uit alle hoeken
                        <span class="italic">van het web.</span>
                    </h2>
                </div>
                <p class="max-w-md text-sm text-ink-soft">
                    Italiaans, Indonesisch, Australisch, Amerikaans. Allemaal automatisch
                    netjes naar metric en Nederlands omgezet.
                </p>
            </div>

            <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
                <div
                    v-for="recipe in showcase"
                    :key="recipe.title"
                    class="group overflow-hidden rounded-3xl bg-cream-soft transition hover:-translate-y-1 hover:shadow-tile-hover"
                >
                    <div class="aspect-[4/3] overflow-hidden">
                        <img
                            :src="recipe.image"
                            :alt="recipe.title"
                            class="h-full w-full object-cover transition duration-500 group-hover:scale-[1.04]"
                            loading="lazy"
                        />
                    </div>
                    <div :class="['flex items-end justify-between gap-3 p-5', blockClass[recipe.block]]">
                        <div>
                            <h3 class="font-display text-xl leading-tight tracking-tight">
                                {{ recipe.title }}
                            </h3>
                            <p class="mt-1 text-xs opacity-70">{{ recipe.meta }}</p>
                        </div>
                        <span
                            class="grid size-9 shrink-0 place-items-center rounded-full bg-ink text-cream transition group-hover:rotate-12"
                        >
                            <ArrowRight class="size-4" />
                        </span>
                    </div>
                </div>
            </div>
        </section>

        <section class="mx-auto max-w-6xl px-4 py-12 md:px-5 md:py-20">
            <div class="mb-10 max-w-2xl">
                <p class="mb-2 text-xs font-semibold uppercase tracking-[0.22em] text-brand">
                    Wat 'm anders maakt
                </p>
                <h2 class="font-display text-3xl tracking-tight md:text-5xl">
                    Niet zomaar een archief.
                    <span class="italic">Een keukenmaatje.</span>
                </h2>
            </div>
            <div class="grid gap-4 md:grid-cols-2 lg:grid-cols-3">
                <div
                    v-for="(feature, idx) in features"
                    :key="feature.title"
                    :class="[
                        'rounded-3xl p-6',
                        idx % 6 === 0 && 'bg-cream-soft',
                        idx % 6 === 1 && 'bg-block-lime',
                        idx % 6 === 2 && 'bg-block-pink',
                        idx % 6 === 3 && 'bg-block-sky',
                        idx % 6 === 4 && 'bg-cream-soft',
                        idx % 6 === 5 && 'bg-ink text-cream',
                    ]"
                >
                    <div
                        :class="[
                            'mb-4 flex size-11 items-center justify-center rounded-full',
                            idx % 6 === 5 ? 'bg-cream text-ink' : 'bg-ink text-cream',
                        ]"
                    >
                        <component :is="feature.icon" class="size-5" />
                    </div>
                    <h3 class="font-display text-xl leading-snug">{{ feature.title }}</h3>
                    <p
                        :class="[
                            'mt-3 text-sm leading-relaxed',
                            idx % 6 === 5 ? 'text-cream/75' : 'text-ink/75',
                        ]"
                    >
                        {{ feature.body }}
                    </p>
                </div>
            </div>
        </section>

        <section class="mx-auto max-w-6xl px-4 pb-16 pt-4 md:px-5 md:pb-24">
            <div class="overflow-hidden rounded-3xl bg-ink px-7 py-12 text-cream md:px-14 md:py-16">
                <div class="grid gap-10 md:grid-cols-[1.4fr_1fr] md:items-end">
                    <div>
                        <p class="mb-3 text-xs font-semibold uppercase tracking-[0.22em] text-brand">
                            Begin vandaag
                        </p>
                        <h2
                            class="font-display text-4xl leading-[1.02] tracking-tight md:text-6xl"
                        >
                            Stop met scrollen door bookmarks.
                            <span class="italic text-brand">Begin je kookboek.</span>
                        </h2>
                        <p class="mt-5 max-w-xl text-base text-cream/70">
                            Gratis voor jezelf. Werkt op je telefoon, tablet en laptop.
                            Importeer je eerste recept binnen één minuut.
                        </p>
                    </div>
                    <div class="flex flex-col gap-3 md:items-end">
                        <Link
                            v-if="$page.props.auth.user"
                            :href="dashboard()"
                            class="inline-flex items-center gap-2 rounded-full bg-brand px-6 py-3 text-base font-medium text-ink transition active:scale-[0.98] hover:bg-[#d35a31]"
                        >
                            Open mijn kookboek
                            <ArrowRight class="size-4" />
                        </Link>
                        <template v-else>
                            <Link
                                v-if="canRegister"
                                :href="register()"
                                class="inline-flex items-center gap-2 rounded-full bg-brand px-6 py-3 text-base font-medium text-ink transition active:scale-[0.98] hover:bg-[#d35a31]"
                            >
                                Maak een gratis account
                                <ArrowRight class="size-4" />
                            </Link>
                            <Link
                                :href="login()"
                                class="text-sm text-cream/70 transition hover:text-cream"
                            >
                                Heb al een account →
                            </Link>
                        </template>
                    </div>
                </div>
            </div>
        </section>

        <footer class="border-t border-rule py-8">
            <div
                class="mx-auto flex max-w-6xl flex-wrap items-center justify-between gap-4 px-5 text-sm text-ink-soft"
            >
                <div class="flex items-center gap-2 font-display italic">
                    <ChefHat class="size-4" />
                    <span>Mijn kookboek — gemaakt voor goed eten</span>
                </div>
                <span class="text-ink-faint">v1</span>
            </div>
        </footer>

        <ActiveCookSessionPill />
    </div>
</template>
