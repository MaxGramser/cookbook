<script setup lang="ts">
import { router } from '@inertiajs/vue3';
import { Loader2 } from 'lucide-vue-next';
import { ref, watch } from 'vue';
import ShortlistController from '@/actions/App/Http/Controllers/ShortlistController';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';

const open = defineModel<boolean>('open', { required: true });

const props = withDefaults(
    defineProps<{
        recipeId?: number | null;
        navigateOnSuccess?: boolean;
    }>(),
    {
        recipeId: null,
        navigateOnSuccess: true,
    },
);

const emit = defineEmits<{
    (e: 'created'): void;
}>();

const name = ref<string>('');
const color = ref<string>('lime');
const processing = ref<boolean>(false);

const colors = [
    { key: 'lime', label: 'Lime', class: 'bg-block-lime' },
    { key: 'pink', label: 'Roze', class: 'bg-block-pink' },
    { key: 'sky', label: 'Lucht', class: 'bg-block-sky' },
    { key: 'cream', label: 'Cream', class: 'bg-cream-soft' },
    { key: 'accent', label: 'Brand', class: 'bg-brand' },
    { key: 'ink', label: 'Inkt', class: 'bg-ink' },
] as const;

watch(open, (next) => {
    if (next) {
        name.value = '';
        color.value = 'lime';
    }
});

function submit(): void {
    if (name.value.trim() === '') {
        return;
    }

    processing.value = true;

    router.post(
        ShortlistController.store.url(),
        {
            name: name.value.trim(),
            color: color.value,
            recipe_id: props.recipeId,
            redirect: props.navigateOnSuccess ? 'show' : 'back',
        },
        {
            preserveScroll: !props.navigateOnSuccess,
            preserveState: !props.navigateOnSuccess,
            onSuccess: () => {
                processing.value = false;
                open.value = false;
                emit('created');

                if (!props.navigateOnSuccess) {
                    router.reload({ only: ['shortlists'] });
                }
            },
            onError: () => {
                processing.value = false;
            },
            onFinish: () => {
                processing.value = false;
            },
        },
    );
}
</script>

<template>
    <Dialog v-model:open="open">
        <DialogContent class="sm:max-w-md">
            <DialogHeader>
                <DialogTitle>Nieuwe shortlist</DialogTitle>
                <DialogDescription>
                    Bundel recepten — handig voor een 3-gangen menu of een
                    weeklijst.
                </DialogDescription>
            </DialogHeader>
            <form class="flex flex-col gap-4" @submit.prevent="submit">
                <div class="grid gap-2">
                    <Label for="shortlist-name">Naam</Label>
                    <Input
                        id="shortlist-name"
                        v-model="name"
                        autofocus
                        autocomplete="off"
                        placeholder="bv. Vrijdagavond"
                        required
                    />
                </div>
                <div class="grid gap-2">
                    <Label>Kleur</Label>
                    <div class="flex flex-wrap gap-2">
                        <button
                            v-for="c in colors"
                            :key="c.key"
                            type="button"
                            :class="[
                                'grid size-8 place-items-center rounded-full border-2 transition active:scale-95',
                                c.class,
                                color === c.key
                                    ? 'border-ink'
                                    : 'border-transparent',
                            ]"
                            :aria-label="c.label"
                            @click="color = c.key"
                        ></button>
                    </div>
                </div>
                <DialogFooter class="mt-2">
                    <Button type="button" variant="ghost" @click="open = false"
                        >Annuleer</Button
                    >
                    <Button
                        type="submit"
                        :disabled="processing || name.trim() === ''"
                    >
                        <Loader2
                            v-if="processing"
                            class="size-4 animate-spin"
                        />
                        Aanmaken
                    </Button>
                </DialogFooter>
            </form>
        </DialogContent>
    </Dialog>
</template>
