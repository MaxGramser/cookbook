<script setup lang="ts">
import { Link, router, usePage } from '@inertiajs/vue3';
import { ListMusic, Plus, Sparkles } from 'lucide-vue-next';
import { computed, onBeforeUnmount, onMounted, ref } from 'vue';
import ShortlistController from '@/actions/App/Http/Controllers/ShortlistController';
import ShortlistCreateDialog from '@/components/ShortlistCreateDialog.vue';
import {
    SidebarGroup,
    SidebarGroupLabel,
    SidebarMenu,
    SidebarMenuButton,
    SidebarMenuItem,
} from '@/components/ui/sidebar';
import { useCurrentUrl } from '@/composables/useCurrentUrl';
import { show as showShortlist } from '@/routes/shortlists';
import type { ShortlistSidebarItem } from '@/types/recipes';

const page = usePage<{ shortlists?: ShortlistSidebarItem[] }>();
const { isCurrentUrl } = useCurrentUrl();

const shortlists = computed<ShortlistSidebarItem[]>(
    () => page.props.shortlists ?? [],
);

const createOpen = ref<boolean>(false);
const dragOverId = ref<number | null>(null);
const isDraggingRecipe = ref<boolean>(false);
const dragOverNew = ref<boolean>(false);
const pendingRecipeId = ref<number | null>(null);

function onDocDragStart(event: DragEvent): void {
    if (!event.dataTransfer?.types.includes('application/x-recipe-id')) {
        return;
    }

    isDraggingRecipe.value = true;
}

function onDocDragEnd(): void {
    isDraggingRecipe.value = false;
    dragOverNew.value = false;
    dragOverId.value = null;
}

onMounted(() => {
    document.addEventListener('dragstart', onDocDragStart);
    document.addEventListener('dragend', onDocDragEnd);
    document.addEventListener('drop', onDocDragEnd);
});

onBeforeUnmount(() => {
    document.removeEventListener('dragstart', onDocDragStart);
    document.removeEventListener('dragend', onDocDragEnd);
    document.removeEventListener('drop', onDocDragEnd);
});

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

function recipeIdFromEvent(event: DragEvent): number | null {
    const id = event.dataTransfer?.getData('application/x-recipe-id');

    if (!id) {
        return null;
    }

    const parsed = Number(id);

    return Number.isFinite(parsed) && parsed > 0 ? parsed : null;
}

function onDragEnter(shortlistId: number, event: DragEvent): void {
    if (
        recipeIdFromEvent(event) === null &&
        !event.dataTransfer?.types.includes('application/x-recipe-id')
    ) {
        return;
    }

    event.preventDefault();
    dragOverId.value = shortlistId;
}

function onDragOver(shortlistId: number, event: DragEvent): void {
    if (!event.dataTransfer?.types.includes('application/x-recipe-id')) {
        return;
    }

    event.preventDefault();

    if (event.dataTransfer) {
        event.dataTransfer.dropEffect = 'copy';
    }

    dragOverId.value = shortlistId;
}

function onDragLeave(shortlistId: number): void {
    if (dragOverId.value === shortlistId) {
        dragOverId.value = null;
    }
}

function onDrop(shortlistId: number, event: DragEvent): void {
    event.preventDefault();
    dragOverId.value = null;
    const recipeId = recipeIdFromEvent(event);

    if (recipeId === null) {
        return;
    }

    router.post(
        ShortlistController.attach.url(shortlistId),
        { recipe_id: recipeId },
        {
            preserveScroll: true,
            preserveState: true,
            only: ['shortlists', 'flash'],
        },
    );
}

function onNewDragOver(event: DragEvent): void {
    if (!event.dataTransfer?.types.includes('application/x-recipe-id')) {
        return;
    }

    event.preventDefault();

    if (event.dataTransfer) {
        event.dataTransfer.dropEffect = 'copy';
    }

    dragOverNew.value = true;
}

function onNewDragLeave(): void {
    dragOverNew.value = false;
}

function onNewDrop(event: DragEvent): void {
    event.preventDefault();
    dragOverNew.value = false;
    const recipeId = recipeIdFromEvent(event);

    if (recipeId === null) {
        return;
    }

    pendingRecipeId.value = recipeId;
    createOpen.value = true;
}

function onCreateClosed(value: boolean): void {
    createOpen.value = value;

    if (!value) {
        pendingRecipeId.value = null;
    }
}
</script>

<template>
    <SidebarGroup class="px-2 py-1">
        <SidebarGroupLabel class="flex items-center justify-between gap-1 pr-1">
            <span class="text-[10px] tracking-[0.2em] uppercase"
                >Shortlists</span
            >
            <button
                type="button"
                aria-label="Nieuwe shortlist"
                class="grid size-5 place-items-center rounded-full bg-ink/10 text-ink-soft transition hover:bg-ink hover:text-cream"
                @click="createOpen = true"
            >
                <Plus class="size-3.5" />
            </button>
        </SidebarGroupLabel>
        <SidebarMenu class="gap-1">
            <SidebarMenuItem
                v-for="shortlist in shortlists"
                :key="shortlist.id"
                @dragenter="onDragEnter(shortlist.id, $event)"
                @dragover="onDragOver(shortlist.id, $event)"
                @dragleave="onDragLeave(shortlist.id)"
                @drop="onDrop(shortlist.id, $event)"
            >
                <SidebarMenuButton
                    as-child
                    :is-active="isCurrentUrl(showShortlist(shortlist.id))"
                    :tooltip="shortlist.name"
                    :class="[
                        'rounded-full font-medium transition data-[active=true]:bg-ink data-[active=true]:text-cream',
                        dragOverId === shortlist.id &&
                            'ring-2 ring-brand ring-offset-1 ring-offset-cream',
                    ]"
                >
                    <Link :href="showShortlist(shortlist.id)">
                        <span
                            :class="[
                                'size-2.5 shrink-0 rounded-full',
                                dotClass(shortlist.color),
                            ]"
                        ></span>
                        <span class="flex-1 truncate">{{
                            shortlist.name
                        }}</span>
                        <span
                            class="ml-1 text-[10px] text-ink-faint tabular-nums"
                        >
                            {{ shortlist.recipe_count }}
                        </span>
                    </Link>
                </SidebarMenuButton>
            </SidebarMenuItem>
            <SidebarMenuItem
                v-if="isDraggingRecipe"
                @dragenter.prevent="dragOverNew = true"
                @dragover="onNewDragOver"
                @dragleave="onNewDragLeave"
                @drop="onNewDrop"
            >
                <SidebarMenuButton
                    as="button"
                    type="button"
                    :class="[
                        'rounded-full border border-dashed font-medium transition',
                        dragOverNew
                            ? 'border-brand bg-brand text-ink ring-2 ring-brand/40'
                            : 'border-rule bg-cream-soft/60 text-ink-soft',
                    ]"
                    @click="createOpen = true"
                >
                    <Sparkles class="size-4" />
                    <span class="flex-1 truncate">Maak nieuwe shortlist</span>
                </SidebarMenuButton>
            </SidebarMenuItem>
            <SidebarMenuItem
                v-if="shortlists.length === 0 && !isDraggingRecipe"
            >
                <SidebarMenuButton
                    as="button"
                    type="button"
                    class="rounded-full text-xs text-ink-faint italic"
                    @click="createOpen = true"
                >
                    <ListMusic class="size-4" />
                    <span>Nog geen shortlists</span>
                </SidebarMenuButton>
            </SidebarMenuItem>
        </SidebarMenu>
    </SidebarGroup>

    <ShortlistCreateDialog
        :open="createOpen"
        :recipe-id="pendingRecipeId"
        :navigate-on-success="true"
        @update:open="onCreateClosed"
    />
</template>
