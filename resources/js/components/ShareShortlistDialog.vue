<script setup lang="ts">
import { router } from '@inertiajs/vue3';
import { Check, Copy, Link2, Loader2, RefreshCw, Trash2 } from 'lucide-vue-next';
import { computed, ref } from 'vue';
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

const open = defineModel<boolean>('open', { required: true });

const props = defineProps<{
    shortlistId: number;
    activeShare: {
        token: string;
        url: string;
        expires_at: string | null;
    } | null;
}>();

const processing = ref<boolean>(false);
const copied = ref<boolean>(false);

const expiryLabel = computed<string>(() => {
    if (!props.activeShare?.expires_at) {
        return '';
    }

    const expires = new Date(props.activeShare.expires_at);
    const diffMs = expires.getTime() - Date.now();

    if (diffMs <= 0) {
        return 'verlopen';
    }

    const days = Math.floor(diffMs / (1000 * 60 * 60 * 24));

    if (days >= 1) {
        return `verloopt over ${days} ${days === 1 ? 'dag' : 'dagen'}`;
    }

    const hours = Math.round(diffMs / (1000 * 60 * 60));

    if (hours >= 1) {
        return `verloopt over ${hours}u`;
    }

    const minutes = Math.max(1, Math.round(diffMs / (1000 * 60)));

    return `verloopt over ${minutes} min`;
});

function generate(): void {
    processing.value = true;

    router.post(
        ShortlistController.share.url(props.shortlistId),
        {},
        {
            preserveScroll: true,
            preserveState: true,
            only: ['shortlist'],
            onFinish: () => {
                processing.value = false;
            },
        },
    );
}

function revoke(): void {
    if (!window.confirm('Deel-link intrekken? De bestaande link werkt dan niet meer.')) {
        return;
    }

    processing.value = true;

    router.delete(ShortlistController.unshare.url(props.shortlistId), {
        preserveScroll: true,
        preserveState: true,
        only: ['shortlist'],
        onFinish: () => {
            processing.value = false;
        },
    });
}

async function copy(): Promise<void> {
    if (!props.activeShare) {
        return;
    }

    try {
        await navigator.clipboard.writeText(props.activeShare.url);
        copied.value = true;
        setTimeout(() => {
            copied.value = false;
        }, 1500);
    } catch {
        // ignore
    }
}
</script>

<template>
    <Dialog v-model:open="open">
        <DialogContent class="sm:max-w-md">
            <DialogHeader>
                <DialogTitle>Deel deze shortlist</DialogTitle>
                <DialogDescription>
                    Iemand met deze link kan de shortlist en recepten
                    bekijken — read-only. De link verloopt na 7 dagen.
                </DialogDescription>
            </DialogHeader>

            <div v-if="activeShare" class="flex flex-col gap-3">
                <div
                    class="flex items-center gap-2 rounded-xl border border-rule bg-cream-soft px-3 py-2"
                >
                    <Link2 class="size-4 shrink-0 text-ink-faint" />
                    <input
                        readonly
                        :value="activeShare.url"
                        class="flex-1 truncate bg-transparent text-sm outline-none"
                        @focus="($event.target as HTMLInputElement).select()"
                    />
                    <button
                        type="button"
                        class="inline-flex items-center gap-1 rounded-full bg-ink px-3 py-1.5 text-xs font-semibold text-cream transition hover:bg-[#3A2C24] active:scale-[0.98]"
                        @click="copy"
                    >
                        <Check v-if="copied" class="size-3.5" />
                        <Copy v-else class="size-3.5" />
                        {{ copied ? 'gekopieerd' : 'kopieer' }}
                    </button>
                </div>
                <p class="text-xs text-ink-soft">
                    {{ expiryLabel }}
                </p>
                <div class="flex flex-wrap gap-2">
                    <button
                        type="button"
                        :disabled="processing"
                        class="inline-flex items-center gap-1.5 rounded-full border border-rule px-3 py-1.5 text-xs font-medium text-ink-soft transition hover:bg-ink/5 disabled:opacity-50"
                        @click="generate"
                    >
                        <RefreshCw v-if="!processing" class="size-3.5" />
                        <Loader2 v-else class="size-3.5 animate-spin" />
                        Nieuwe link genereren
                    </button>
                    <button
                        type="button"
                        :disabled="processing"
                        class="inline-flex items-center gap-1.5 rounded-full border border-warn/30 px-3 py-1.5 text-xs font-medium text-warn transition hover:bg-warn/5 disabled:opacity-50"
                        @click="revoke"
                    >
                        <Trash2 class="size-3.5" />
                        Link intrekken
                    </button>
                </div>
            </div>

            <div v-else class="flex flex-col gap-3">
                <p class="text-sm text-ink-soft">
                    Er is nog geen actieve deel-link. Genereer er een om te
                    delen via WhatsApp, mail, of waar dan ook.
                </p>
                <Button
                    type="button"
                    :disabled="processing"
                    class="self-start"
                    @click="generate"
                >
                    <Loader2 v-if="processing" class="size-4 animate-spin" />
                    <Link2 v-else class="size-4" />
                    Genereer deel-link
                </Button>
            </div>

            <DialogFooter>
                <Button type="button" variant="ghost" @click="open = false">
                    Sluiten
                </Button>
            </DialogFooter>
        </DialogContent>
    </Dialog>
</template>
