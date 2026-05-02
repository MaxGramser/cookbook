<script setup lang="ts">
import { Form } from '@inertiajs/vue3';
import { Loader2 } from 'lucide-vue-next';
import RecipeImportController from '@/actions/App/Http/Controllers/RecipeImportController';
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
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';

const open = defineModel<boolean>('open', { required: true });
</script>

<template>
    <Dialog v-model:open="open">
        <DialogContent class="sm:max-w-md">
            <DialogHeader>
                <DialogTitle>Importeer via URL</DialogTitle>
                <DialogDescription>
                    Plak een link naar een receptenpagina. Wij halen 'm op en zetten het automatisch in je
                    kookboek.
                </DialogDescription>
            </DialogHeader>
            <Form
                v-bind="RecipeImportController.store.form()"
                class="flex flex-col gap-3"
                v-slot="{ errors, processing }"
            >
                <div class="grid gap-2">
                    <Label for="url">URL</Label>
                    <Input
                        id="url"
                        name="url"
                        type="url"
                        autocomplete="off"
                        autofocus
                        placeholder="https://..."
                        required
                    />
                    <InputError :message="errors.url" />
                </div>
                <DialogFooter class="mt-2">
                    <Button type="button" variant="ghost" @click="open = false">Annuleer</Button>
                    <Button type="submit" :disabled="processing">
                        <Loader2 v-if="processing" class="size-4 animate-spin" />
                        Importeer
                    </Button>
                </DialogFooter>
            </Form>
        </DialogContent>
    </Dialog>
</template>
