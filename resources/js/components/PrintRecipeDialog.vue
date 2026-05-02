<script setup lang="ts">
import { Minus, Plus, Printer, Users } from 'lucide-vue-next';
import { computed, onBeforeUnmount, onMounted, ref, watch } from 'vue';
import PrintableRecipe from '@/components/PrintableRecipe.vue';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import type { Recipe } from '@/types/recipes';

const props = defineProps<{ recipe: Recipe }>();

const open = defineModel<boolean>('open', { required: true });
const multiplier = ref<number>(1);

watch(open, (isOpen) => {
    if (isOpen) {
        multiplier.value = 1;
    }
});

const scaledServings = computed(() => Math.round(props.recipe.servings * multiplier.value));

function bump(delta: number): void {
    const next = Math.round((multiplier.value + delta) * 4) / 4;
    multiplier.value = Math.max(0.25, Math.min(20, next));
}

function setServings(target: number): void {
    multiplier.value = Math.max(0.25, Math.min(20, target / props.recipe.servings));
}

function onAfterPrint(): void {
    document.body.classList.remove('is-printing-recipe');
}

onMounted(() => {
    window.addEventListener('afterprint', onAfterPrint);
});
onBeforeUnmount(() => {
    window.removeEventListener('afterprint', onAfterPrint);
    document.body.classList.remove('is-printing-recipe');
});

function doPrint(): void {
    document.body.classList.add('is-printing-recipe');
    open.value = false;
    requestAnimationFrame(() => {
        requestAnimationFrame(() => {
            window.print();
        });
    });
}
</script>

<template>
    <Dialog v-model:open="open">
        <DialogContent class="sm:max-w-md">
            <DialogHeader>
                <DialogTitle>Recept printen</DialogTitle>
                <DialogDescription>
                    Kies voor hoeveel personen je wilt printen. Hoeveelheden worden automatisch geschaald.
                </DialogDescription>
            </DialogHeader>

            <div class="flex flex-col gap-4 py-2">
                <div class="flex items-center gap-3 rounded-xl bg-muted/50 px-4 py-3">
                    <Users class="size-5 text-muted-foreground" />
                    <div class="flex-1">
                        <div class="text-2xl font-semibold tabular-nums">{{ scaledServings }}</div>
                        <div class="text-xs text-muted-foreground">
                            origineel: {{ recipe.servings }} · ×{{ multiplier }}
                        </div>
                    </div>
                    <div class="flex items-center gap-1">
                        <Button
                            type="button"
                            variant="outline"
                            size="icon"
                            class="size-9"
                            :disabled="multiplier <= 0.25"
                            @click="bump(-0.25)"
                        >
                            <Minus class="size-4" />
                        </Button>
                        <Button
                            type="button"
                            variant="outline"
                            size="icon"
                            class="size-9"
                            @click="bump(0.25)"
                        >
                            <Plus class="size-4" />
                        </Button>
                    </div>
                </div>

                <div class="flex flex-wrap gap-2">
                    <Button
                        v-for="preset in [1, 2, 4, 6, 8]"
                        :key="preset"
                        type="button"
                        variant="outline"
                        size="sm"
                        :class="
                            scaledServings === preset
                                ? 'border-primary bg-primary text-primary-foreground hover:bg-primary/90 hover:text-primary-foreground'
                                : ''
                        "
                        @click="setServings(preset)"
                    >
                        {{ preset }}p
                    </Button>
                </div>
            </div>

            <DialogFooter>
                <Button type="button" variant="ghost" @click="open = false">Annuleer</Button>
                <Button type="button" @click="doPrint">
                    <Printer class="size-4" /> Print
                </Button>
            </DialogFooter>
        </DialogContent>
    </Dialog>

    <Teleport to="body">
        <div id="print-portal">
            <PrintableRecipe :recipe="recipe" :multiplier="multiplier" />
        </div>
    </Teleport>
</template>
