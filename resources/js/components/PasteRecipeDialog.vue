<script setup lang="ts">
import { useForm } from '@inertiajs/vue3';
import { ImageIcon, Loader2, X } from 'lucide-vue-next';
import { ref, watch } from 'vue';
import InputError from '@/components/InputError.vue';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import { Label } from '@/components/ui/label';
import { text as importText } from '@/routes/recipes/import';

const open = defineModel<boolean>('open', { required: true });

const form = useForm({
    text: '',
    image: null as File | null,
});

const imagePreview = ref<string | null>(null);

watch(open, (isOpen) => {
    if (isOpen) {
        form.reset();
        form.clearErrors();
        imagePreview.value = null;
    }
});

function onImage(event: Event): void {
    const target = event.target as HTMLInputElement;
    const file = target.files?.[0] ?? null;
    form.image = file;
    if (file) {
        imagePreview.value = URL.createObjectURL(file);
    } else {
        imagePreview.value = null;
    }
}

function clearImage(): void {
    form.image = null;
    imagePreview.value = null;
}

function submit(): void {
    form.post(importText().url, {
        forceFormData: true,
        onSuccess: () => {
            open.value = false;
        },
    });
}
</script>

<template>
    <Dialog v-model:open="open">
        <DialogContent class="sm:max-w-lg">
            <DialogHeader>
                <DialogTitle>Plak een recept</DialogTitle>
                <DialogDescription>
                    Kopieer de caption / tekst van bv. Instagram, TikTok, een blogpost, of een mailtje.
                    Optioneel een foto erbij.
                </DialogDescription>
            </DialogHeader>

            <form class="flex flex-col gap-3" @submit.prevent="submit">
                <div class="grid gap-2">
                    <Label for="text">Recept-tekst</Label>
                    <textarea
                        id="text"
                        v-model="form.text"
                        autofocus
                        rows="10"
                        class="min-h-[180px] rounded-md border border-input bg-transparent px-3 py-2 text-sm shadow-xs outline-none focus-visible:border-ring focus-visible:ring-2 focus-visible:ring-ring/50"
                        placeholder="Plak hier de hele tekst van het recept — met ingrediënten en stappen..."
                        required
                    />
                    <InputError :message="form.errors.text" />
                </div>

                <div class="grid gap-2">
                    <Label for="image">Afbeelding (optioneel)</Label>
                    <div
                        v-if="imagePreview"
                        class="relative w-full overflow-hidden rounded-md border border-input"
                    >
                        <img :src="imagePreview" class="aspect-video w-full object-cover" alt="preview" />
                        <button
                            type="button"
                            class="absolute right-2 top-2 flex size-7 items-center justify-center rounded-full bg-background/80 backdrop-blur"
                            aria-label="Verwijder foto"
                            @click="clearImage"
                        >
                            <X class="size-4" />
                        </button>
                    </div>
                    <label
                        v-else
                        class="flex cursor-pointer items-center justify-center gap-2 rounded-md border border-dashed border-input px-3 py-6 text-sm text-muted-foreground hover:bg-muted/50"
                    >
                        <ImageIcon class="size-4" />
                        <span>Foto kiezen</span>
                        <input
                            id="image"
                            type="file"
                            accept="image/*"
                            class="hidden"
                            @change="onImage"
                        />
                    </label>
                    <InputError :message="form.errors.image" />
                </div>

                <DialogFooter class="mt-2">
                    <Button type="button" variant="ghost" @click="open = false">Annuleer</Button>
                    <Button type="submit" :disabled="form.processing">
                        <Loader2 v-if="form.processing" class="size-4 animate-spin" />
                        Maak recept
                    </Button>
                </DialogFooter>
            </form>
        </DialogContent>
    </Dialog>
</template>
