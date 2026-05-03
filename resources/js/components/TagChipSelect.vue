<script setup lang="ts">
import { Check, Loader2, Plus, X } from 'lucide-vue-next';
import { computed, ref } from 'vue';
import { store as storeTagRoute } from '@/routes/tags';
import type { Tag, TagColor, TagGroup } from '@/types/recipes';

type Mode = 'edit' | 'filter';

const props = withDefaults(
    defineProps<{
        modelValue: number[];
        tags: Tag[];
        mode?: Mode;
        // Optional cap per group; cuisine defaults to 2 in edit mode.
        groupCaps?: Partial<Record<TagGroup, number>>;
        // Hide the "+" add-tag affordance (e.g. on the filter bar).
        allowCreate?: boolean;
    }>(),
    {
        mode: 'edit',
        groupCaps: () => ({}),
        allowCreate: true,
    },
);

const emit = defineEmits<{
    (e: 'update:modelValue', value: number[]): void;
    (e: 'tag-created', tag: Tag): void;
}>();

const groupOrder: TagGroup[] = ['meal_type', 'cuisine', 'attribute'];

const groupLabels: Record<TagGroup, string> = {
    meal_type: 'Type maaltijd',
    cuisine: 'Keuken',
    attribute: 'Eigenschappen',
};

const groupHints: Record<TagGroup, string> = {
    meal_type: 'Wanneer eet je dit',
    cuisine: 'Culinaire stijl',
    attribute: 'Karakter & dieet',
};

const localTags = ref<Tag[]>([...props.tags]);

const groupedTags = computed<Record<TagGroup, Tag[]>>(() => {
    const groups: Record<TagGroup, Tag[]> = {
        meal_type: [],
        cuisine: [],
        attribute: [],
    };

    for (const tag of localTags.value) {
        groups[tag.group].push(tag);
    }

    return groups;
});

const selected = computed(() => new Set(props.modelValue));

const effectiveCap: Record<TagGroup, number | undefined> = {
    meal_type: props.groupCaps.meal_type,
    cuisine: props.mode === 'edit' ? (props.groupCaps.cuisine ?? 2) : props.groupCaps.cuisine,
    attribute: props.groupCaps.attribute,
};

function selectedCountForGroup(group: TagGroup): number {
    return groupedTags.value[group].filter((t) => selected.value.has(t.id)).length;
}

function isCapped(group: TagGroup, tagId: number): boolean {
    const cap = effectiveCap[group];

    if (cap === undefined) {
        return false;
    }

    if (selected.value.has(tagId)) {
        return false;
    }

    return selectedCountForGroup(group) >= cap;
}

function toggle(tag: Tag): void {
    const set = new Set(props.modelValue);

    if (set.has(tag.id)) {
        set.delete(tag.id);
    } else {
        if (isCapped(tag.group, tag.id)) {
            return;
        }

        set.add(tag.id);
    }

    emit('update:modelValue', Array.from(set));
}

const addingFor = ref<TagGroup | null>(null);
const newName = ref<string>('');
const submitting = ref<boolean>(false);
const addError = ref<string | null>(null);

function startAdd(group: TagGroup): void {
    addingFor.value = group;
    newName.value = '';
    addError.value = null;
}

function cancelAdd(): void {
    addingFor.value = null;
    newName.value = '';
    addError.value = null;
}

async function submitAdd(): Promise<void> {
    if (!addingFor.value) {
        return;
    }

    const name = newName.value.trim();

    if (name === '') {
        cancelAdd();

        return;
    }

    submitting.value = true;
    addError.value = null;

    try {
        const csrf =
            (document.querySelector('meta[name="csrf-token"]') as HTMLMetaElement | null)?.content ?? '';
        const response = await fetch(storeTagRoute().url, {
            method: 'POST',
            credentials: 'same-origin',
            headers: {
                'Content-Type': 'application/json',
                Accept: 'application/json',
                'X-Requested-With': 'XMLHttpRequest',
                'X-CSRF-TOKEN': csrf,
            },
            body: JSON.stringify({ group: addingFor.value, name }),
        });

        if (!response.ok) {
            const payload = await response.json().catch(() => null);
            addError.value = payload?.message ?? 'Kon tag niet toevoegen';

            return;
        }

        const payload = (await response.json()) as { tag: Tag };
        const tag = payload.tag;

        if (!localTags.value.some((t) => t.id === tag.id)) {
            localTags.value = [...localTags.value, tag];
        }

        emit('tag-created', tag);

        const set = new Set(props.modelValue);

        if (!isCapped(tag.group, tag.id)) {
            set.add(tag.id);
            emit('update:modelValue', Array.from(set));
        }

        cancelAdd();
    } catch {
        addError.value = 'Kon tag niet toevoegen';
    } finally {
        submitting.value = false;
    }
}

const colorActiveClass: Record<TagColor, string> = {
    cream: 'bg-ink text-cream',
    lime: 'bg-block-lime text-ink',
    pink: 'bg-block-pink text-ink',
    sky: 'bg-block-sky text-ink',
    accent: 'bg-brand text-ink',
    ink: 'bg-ink text-cream',
};

function chipClass(tag: Tag, capped: boolean): string {
    if (selected.value.has(tag.id)) {
        return `${colorActiveClass[tag.color] ?? colorActiveClass.cream} border-transparent shadow-tile`;
    }

    if (capped) {
        return 'border-rule bg-cream-soft text-ink-faint cursor-not-allowed opacity-60';
    }

    return 'border-rule bg-cream-soft text-ink-soft hover:bg-ink/5';
}
</script>

<template>
    <div class="flex flex-col gap-4">
        <div v-for="group in groupOrder" :key="group" class="flex flex-col gap-2">
            <div class="flex items-baseline justify-between">
                <div class="flex items-baseline gap-2">
                    <h3 class="text-[11px] font-semibold uppercase tracking-[0.2em] text-ink-soft">
                        {{ groupLabels[group] }}
                    </h3>
                    <span class="text-[11px] text-ink-faint">{{ groupHints[group] }}</span>
                </div>
                <span
                    v-if="effectiveCap[group] !== undefined"
                    class="text-[11px] tabular-nums text-ink-faint"
                >
                    {{ selectedCountForGroup(group) }} / {{ effectiveCap[group] }}
                </span>
            </div>

            <div class="flex flex-wrap gap-1.5">
                <button
                    v-for="tag in groupedTags[group]"
                    :key="tag.id"
                    type="button"
                    :disabled="isCapped(group, tag.id)"
                    :class="[
                        'inline-flex items-center gap-1.5 rounded-full border px-3 py-1.5 text-xs font-semibold transition active:scale-[0.97]',
                        chipClass(tag, isCapped(group, tag.id)),
                    ]"
                    @click="toggle(tag)"
                >
                    <Check v-if="selected.has(tag.id)" class="size-3.5" />
                    <span>{{ tag.name }}</span>
                </button>

                <template v-if="allowCreate">
                    <button
                        v-if="addingFor !== group"
                        type="button"
                        class="inline-flex items-center gap-1.5 rounded-full border border-dashed border-rule bg-transparent px-3 py-1.5 text-xs font-semibold text-ink-soft transition hover:bg-ink/5"
                        @click="startAdd(group)"
                    >
                        <Plus class="size-3.5" />
                        Eigen
                    </button>

                    <form
                        v-else
                        class="inline-flex items-center gap-1.5 rounded-full border border-brand bg-cream-soft pl-3 pr-1 py-1 text-xs"
                        @submit.prevent="submitAdd"
                    >
                        <input
                            v-model="newName"
                            type="text"
                            maxlength="40"
                            :placeholder="`Nieuwe ${groupLabels[group].toLowerCase()}`"
                            class="w-44 bg-transparent text-xs font-semibold outline-none placeholder:text-ink-faint"
                            autofocus
                            @keydown.escape="cancelAdd"
                        />
                        <button
                            type="submit"
                            :disabled="submitting"
                            class="grid size-6 place-items-center rounded-full bg-ink text-cream transition active:scale-90 disabled:opacity-50"
                        >
                            <Loader2 v-if="submitting" class="size-3.5 animate-spin" />
                            <Check v-else class="size-3.5" />
                        </button>
                        <button
                            type="button"
                            class="grid size-6 place-items-center rounded-full text-ink-faint transition hover:bg-ink/10"
                            @click="cancelAdd"
                        >
                            <X class="size-3.5" />
                        </button>
                    </form>
                </template>
            </div>
            <p v-if="addingFor === group && addError" class="text-xs text-warn">
                {{ addError }}
            </p>
        </div>
    </div>
</template>
