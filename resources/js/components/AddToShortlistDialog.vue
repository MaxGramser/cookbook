<script setup lang="ts">
import { router, usePage } from '@inertiajs/vue3';
import { Check, ListMusic, Plus } from 'lucide-vue-next';
import { ref } from 'vue';
import ShortlistController from '@/actions/App/Http/Controllers/ShortlistController';
import ShortlistCreateDialog from '@/components/ShortlistCreateDialog.vue';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import type { ShortlistSidebarItem } from '@/types/recipes';

const open = defineModel<boolean>('open', { required: true });

const props = defineProps<{
    recipeId: number;
}>();

const page = usePage<{ shortlists?: ShortlistSidebarItem[] }>();

const recentlyAddedTo = ref<Set<number>>(new Set());
const createOpen = ref<boolean>(false);

const colorDot: Record<string, string> = {
    lime: 'bg-block-lime',
    pink: 'bg-block-pink',
    sky: 'bg-block-sky',
    cream: 'bg-cream-soft border border-rule',
    accent: 'bg-brand',
    ink: 'bg-ink',
};

function dotClass(color: string | null): string {
    return color ? (colorDot[color] ?? 'bg-ink/20') : 'bg-ink/20';
}

function attach(shortlistId: number): void {
    router.post(
        ShortlistController.attach.url(shortlistId),
        { recipe_id: props.recipeId },
        {
            preserveScroll: true,
            preserveState: true,
            only: ['shortlists'],
            onSuccess: () => {
                recentlyAddedTo.value.add(shortlistId);
                recentlyAddedTo.value = new Set(recentlyAddedTo.value);
            },
        },
    );
}
</script>

<template>
    <Dialog v-model:open="open">
        <DialogContent class="sm:max-w-md">
            <DialogHeader>
                <DialogTitle>Toevoegen aan shortlist</DialogTitle>
                <DialogDescription>
                    Kies een shortlist of maak een nieuwe aan.
                </DialogDescription>
            </DialogHeader>

            <div class="flex flex-col gap-1">
                <button
                    type="button"
                    class="flex items-center gap-3 rounded-xl border border-rule bg-cream-soft px-3 py-2.5 text-sm transition hover:bg-block-cream"
                    @click="createOpen = true"
                >
                    <span
                        class="grid size-9 shrink-0 place-items-center rounded-full bg-ink text-cream"
                    >
                        <Plus class="size-4" />
                    </span>
                    <span class="flex-1 text-left font-semibold"
                        >Nieuwe shortlist</span
                    >
                </button>

                <div
                    v-if="(page.props.shortlists ?? []).length === 0"
                    class="rounded-xl border border-dashed border-rule bg-cream p-6 text-center text-sm text-ink-soft"
                >
                    <ListMusic class="mx-auto mb-2 size-5 text-ink-faint" />
                    Nog geen shortlists. Maak er eentje aan om te beginnen.
                </div>

                <button
                    v-for="shortlist in page.props.shortlists ?? []"
                    :key="shortlist.id"
                    type="button"
                    class="flex items-center gap-3 rounded-xl border border-transparent px-3 py-2.5 text-sm transition hover:border-rule hover:bg-cream-soft"
                    @click="attach(shortlist.id)"
                >
                    <span
                        :class="[
                            'size-3 shrink-0 rounded-full',
                            dotClass(shortlist.color),
                        ]"
                    ></span>
                    <span class="flex-1 text-left font-medium">{{
                        shortlist.name
                    }}</span>
                    <span class="text-[10px] text-ink-faint tabular-nums">
                        {{ shortlist.recipe_count }}
                    </span>
                    <span
                        v-if="recentlyAddedTo.has(shortlist.id)"
                        class="grid size-6 place-items-center rounded-full bg-block-lime text-ink"
                    >
                        <Check class="size-3.5" />
                    </span>
                </button>
            </div>
        </DialogContent>
    </Dialog>

    <ShortlistCreateDialog
        v-model:open="createOpen"
        :recipe-id="recipeId"
        :navigate-on-success="false"
        @created="open = false"
    />
</template>
