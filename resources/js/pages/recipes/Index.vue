<script setup lang="ts">
import { Form, Head, Link } from '@inertiajs/vue3';
import { Clock, Plus, Users } from 'lucide-vue-next';
import RecipeImportController from '@/actions/App/Http/Controllers/RecipeImportController';
import Heading from '@/components/Heading.vue';
import InputError from '@/components/InputError.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { dashboard } from '@/routes';
import { create as createRecipe, show as showRecipe } from '@/routes/recipes';
import type { RecipeSummary } from '@/types/recipes';

defineProps<{ recipes: RecipeSummary[] }>();

defineOptions({
    layout: { breadcrumbs: [{ title: 'Recepten', href: dashboard() }] },
});
</script>

<template>
    <Head title="Recepten" />

    <div class="flex flex-col gap-6 p-4 md:p-6">
        <div class="flex flex-wrap items-end justify-between gap-4">
            <Heading title="Mijn recepten" description="Jouw persoonlijke kookboek" />
            <Button as-child>
                <Link :href="createRecipe()">
                    <Plus class="size-4" /> Nieuw recept
                </Link>
            </Button>
        </div>

        <div class="rounded-xl border border-sidebar-border/70 p-4 md:p-6 dark:border-sidebar-border">
            <Form
                v-bind="RecipeImportController.store.form()"
                class="flex flex-col gap-3 sm:flex-row sm:items-end"
                v-slot="{ errors, processing }"
            >
                <div class="flex-1">
                    <Label for="url">Importeer recept via URL</Label>
                    <Input
                        id="url"
                        name="url"
                        type="url"
                        placeholder="https://..."
                        autocomplete="off"
                    />
                    <InputError class="mt-1" :message="errors.url" />
                </div>
                <Button type="submit" :disabled="processing">Importeer</Button>
            </Form>
        </div>

        <div v-if="recipes.length === 0" class="rounded-xl border border-dashed p-12 text-center">
            <p class="text-muted-foreground">Nog geen recepten — voeg er eentje toe of importeer er een.</p>
        </div>

        <div v-else class="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4">
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
    </div>
</template>
