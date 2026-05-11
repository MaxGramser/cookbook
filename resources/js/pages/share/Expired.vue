<script setup lang="ts">
import { Head } from '@inertiajs/vue3';
import { ChefHat, Clock4 } from 'lucide-vue-next';
</script>

<template>
    <Head>
        <title>Link verlopen — CookBook</title>
        <meta head-key="robots" name="robots" content="noindex, nofollow" />
    </Head>

    <div
        class="flex min-h-svh flex-col items-center justify-center bg-cream px-4 text-center font-sans text-ink"
    >
        <div
            class="grid size-14 place-items-center rounded-full bg-ink text-cream"
        >
            <Clock4 class="size-6" />
        </div>
        <h1 class="font-display mt-5 text-3xl tracking-tight md:text-4xl">
            Deze deel-link is verlopen.
        </h1>
        <p class="mt-3 max-w-md text-sm leading-relaxed text-ink-soft">
            Vraag de eigenaar om een nieuwe link te genereren en op te sturen.
        </p>
        <p
            class="mt-8 inline-flex items-center gap-2 rounded-full bg-cream-soft px-4 py-2 text-[11px] font-semibold tracking-[0.18em] text-ink-faint uppercase"
        >
            <ChefHat class="size-3.5" />
            CookBook
        </p>
    </div>
</template>
