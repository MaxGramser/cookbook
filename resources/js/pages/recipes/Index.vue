<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import {
    ChevronDown,
    Clock,
    ClipboardPaste,
    Link2,
    PencilLine,
    Plus,
    Users,
} from 'lucide-vue-next';
import { ref } from 'vue';
import Heading from '@/components/Heading.vue';
import ImportUrlDialog from '@/components/ImportUrlDialog.vue';
import PasteRecipeDialog from '@/components/PasteRecipeDialog.vue';
import { Button } from '@/components/ui/button';
import {
    DropdownMenu,
    DropdownMenuContent,
    DropdownMenuItem,
    DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu';
import { dashboard } from '@/routes';
import { create as createRecipe, show as showRecipe } from '@/routes/recipes';
import type { RecipeSummary } from '@/types/recipes';

defineProps<{ recipes: RecipeSummary[] }>();

defineOptions({
    layout: { breadcrumbs: [{ title: 'Recepten', href: dashboard() }] },
});

const urlOpen = ref<boolean>(false);
const pasteOpen = ref<boolean>(false);
</script>

<template>
    <Head title="Recepten" />

    <div class="flex flex-col gap-6 p-4 md:p-6">
        <div class="flex flex-wrap items-end justify-between gap-4">
            <Heading title="Mijn recepten" description="Jouw persoonlijke kookboek" />

            <DropdownMenu>
                <DropdownMenuTrigger as-child>
                    <Button>
                        <Plus class="size-4" /> Recept toevoegen
                        <ChevronDown class="size-4 opacity-70" />
                    </Button>
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

        <div v-if="recipes.length === 0" class="rounded-xl border border-dashed p-12 text-center">
            <p class="text-muted-foreground">
                Nog geen recepten — klik op "Recept toevoegen" om te beginnen.
            </p>
        </div>

        <div
            v-else
            class="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4"
        >
            <Link
                v-for="recipe in recipes"
                :key="recipe.id"
                :href="showRecipe(recipe.id)"
                class="group overflow-hidden rounded-xl border border-sidebar-border/70 transition hover:border-foreground/40 dark:border-sidebar-border"
            >
                <div class="aspect-video w-full bg-muted">
                    <img
                        v-if="recipe.image_path"
                        :src="`/storage/${recipe.image_path}`"
                        :alt="recipe.title"
                        class="h-full w-full object-cover transition group-hover:scale-[1.02]"
                    />
                </div>
                <div class="p-4">
                    <h3 class="line-clamp-2 font-semibold">{{ recipe.title }}</h3>
                    <div class="mt-2 flex gap-3 text-xs text-muted-foreground">
                        <span v-if="recipe.cook_time_minutes" class="flex items-center gap-1">
                            <Clock class="size-3.5" /> {{ recipe.cook_time_minutes }} min
                        </span>
                        <span class="flex items-center gap-1">
                            <Users class="size-3.5" /> {{ recipe.servings }}
                        </span>
                    </div>
                </div>
            </Link>
        </div>

        <ImportUrlDialog v-model:open="urlOpen" />
        <PasteRecipeDialog v-model:open="pasteOpen" />
    </div>
</template>
