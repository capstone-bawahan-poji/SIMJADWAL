<script setup lang="ts">
import { Search, X } from 'lucide-vue-next';
import { computed, ref, useId } from 'vue';

type Option = { value: string | number; label: string; hint?: string };

const props = defineProps<{ options: Option[]; placeholder: string; label: string }>();
const model = defineModel<string | number>({ required: true });

const query = ref('');
const open = ref(false);
const active = ref(0);
const listId = useId();

const selected = computed(() => props.options.find((o) => o.value === model.value) ?? null);
const matches = computed(() => {
    const q = query.value.trim().toLowerCase();
    const list = q ? props.options.filter((o) => `${o.label} ${o.hint ?? ''}`.toLowerCase().includes(q)) : props.options;
    return list.slice(0, 50);
});

function show(): void {
    query.value = '';
    active.value = 0;
    open.value = true;
}

function pick(option: Option): void {
    model.value = option.value;
    open.value = false;
}

function clear(): void {
    model.value = '';
    query.value = '';
}

function move(step: number): void {
    if (!open.value) return show();
    active.value = Math.min(Math.max(active.value + step, 0), Math.max(matches.value.length - 1, 0));
}

function onInput(event: Event): void {
    query.value = (event.target as HTMLInputElement).value;
    active.value = 0;
    open.value = true;
}

function onFocusOut(event: FocusEvent): void {
    if (!(event.currentTarget as HTMLElement).contains(event.relatedTarget as Node | null)) open.value = false;
}
</script>

<!-- Type-ahead filter: type to narrow the options, pick one, clear with the X. -->
<template>
    <div class="relative" @focusout="onFocusOut">
        <Search class="pointer-events-none absolute left-3 top-1/2 size-4 -translate-y-1/2 text-gray-400" aria-hidden="true" />
        <input
            type="text"
            role="combobox"
            autocomplete="off"
            class="h-10 w-full rounded-lg border-gray-200 bg-white pl-9 pr-9 text-sm text-gray-700 placeholder:text-gray-400 focus:border-primary focus:ring-primary"
            :aria-label="label"
            :aria-expanded="open"
            :aria-controls="listId"
            aria-autocomplete="list"
            :placeholder="placeholder"
            :value="open ? query : (selected?.label ?? '')"
            @focus="show"
            @input="onInput"
            @keydown.down.prevent="move(1)"
            @keydown.up.prevent="move(-1)"
            @keydown.enter.prevent="matches[active] && pick(matches[active])"
            @keydown.esc="open = false"
        />
        <button v-if="model !== ''" type="button" class="absolute right-2 top-1/2 -translate-y-1/2 rounded p-1 text-gray-400 hover:text-gray-700 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-primary" :aria-label="`Hapus pilihan ${label}`" @click="clear">
            <X class="size-4" aria-hidden="true" />
        </button>
        <ul v-if="open" :id="listId" role="listbox" class="absolute z-30 mt-1 max-h-64 w-full overflow-auto rounded-lg border border-gray-200 bg-white py-1 shadow-lg">
            <li v-if="matches.length === 0" class="px-3 py-2 text-sm text-gray-500">Tidak ada hasil untuk "{{ query }}".</li>
            <li
                v-for="(option, i) in matches"
                :key="option.value"
                role="option"
                :aria-selected="option.value === model"
                class="cursor-pointer px-3 py-2 text-sm text-gray-700"
                :class="i === active ? 'bg-gray-100' : ''"
                @mousedown.prevent="pick(option)"
                @mousemove="active = i"
            >
                {{ option.label }}<span v-if="option.hint" class="block text-xs text-gray-500">{{ option.hint }}</span>
            </li>
        </ul>
    </div>
</template>
