<script setup lang="ts">
import { Link, usePage } from '@inertiajs/vue3';
import { Bike, Home, ShoppingBasket } from 'lucide-vue-next';
import { computed } from 'vue';
import { show as showGrocerySession } from '@/routes/grocery';
import type { ActiveGrocerySession } from '@/types/global';

const page = usePage();

const activeSession = computed<ActiveGrocerySession | null>(() => {
    return (page.props.activeGrocerySession as ActiveGrocerySession | null) ?? null;
});

const isOnSessionPage = computed<boolean>(() => {
    if (activeSession.value === null) {
        return false;
    }

    return page.url.startsWith(`/grocery/${activeSession.value.id}`);
});

const visible = computed<boolean>(() => activeSession.value !== null && !isOnSessionPage.value);
const isShopping = computed<boolean>(() => activeSession.value?.phase === 'shopping');

const offsetClass = computed<string>(() => {
    const hasCookSession = (page.props.activeCookSession as unknown) !== null;

    return hasCookSession ? 'bottom-20' : 'bottom-4';
});
</script>

<template>
    <Transition
        enter-active-class="transition duration-300 ease-out"
        enter-from-class="opacity-0 translate-y-4 scale-95"
        enter-to-class="opacity-100 translate-y-0 scale-100"
        leave-active-class="transition duration-200 ease-in"
        leave-from-class="opacity-100 translate-y-0 scale-100"
        leave-to-class="opacity-0 translate-y-4 scale-95"
    >
        <Link
            v-if="visible && activeSession"
            :href="showGrocerySession(activeSession.id)"
            :class="[
                'group fixed right-4 z-50 flex items-center gap-2.5 rounded-full px-3 py-2 pr-4 shadow-tile transition active:scale-[0.97] hover:shadow-tile-hover',
                offsetClass,
                isShopping ? 'bg-block-lime text-ink' : 'bg-block-sky text-ink',
            ]"
        >
            <span
                class="relative grid size-9 shrink-0 place-items-center rounded-full bg-ink text-cream"
            >
                <ShoppingBasket v-if="isShopping" class="size-4" />
                <Home v-else class="size-4" />
            </span>
            <div class="flex flex-col leading-tight">
                <span class="text-[10px] font-semibold uppercase tracking-[0.2em] text-ink/65">
                    {{ isShopping ? 'In de winkel' : 'Thuis afstrepen' }}
                </span>
                <span class="flex items-center gap-2 text-sm font-semibold">
                    <span class="line-clamp-1 max-w-[12rem]">
                        {{ activeSession.recipe_title }}
                    </span>
                    <Bike v-if="isShopping" class="size-3.5 text-ink/70" />
                </span>
            </div>
        </Link>
    </Transition>
</template>
