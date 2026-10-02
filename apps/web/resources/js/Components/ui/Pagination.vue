<script setup lang="ts">
import Button from '@/Components/ui/Button.vue';
import type { Pagination } from '@/types/models';
import { computed } from 'vue';

const props = defineProps<{ pagination: Pagination; label?: string }>();
const page = defineModel<number>('page', { required: true });

const from = computed(() => (props.pagination.total === 0 ? 0 : (props.pagination.page - 1) * props.pagination.per_page + 1));
const to = computed(() => Math.min(props.pagination.page * props.pagination.per_page, props.pagination.total));

const pages = computed(() => {
    const last = props.pagination.last_page;
    const current = props.pagination.page;
    const shown = [...new Set([1, current - 1, current, current + 1, last])].filter((p) => p >= 1 && p <= last).sort((a, b) => a - b);

    return shown.flatMap((p, i) => (i > 0 && p - shown[i - 1] > 1 ? [null, p] : [p]));
});
</script>

<template>
    <div class="flex flex-col items-center justify-between gap-3 border-t border-gray-100 bg-white px-5 py-4 sm:flex-row">
        <p class="text-sm text-gray-500">
            Menampilkan <span class="font-semibold text-gray-900">{{ from }}–{{ to }}</span> dari
            <span class="font-semibold text-gray-900">{{ pagination.total }}</span> {{ label ?? 'data' }}
        </p>
        <nav v-if="pagination.last_page > 1" class="flex flex-wrap items-center gap-1" aria-label="Halaman">
            <Button variant="ghost" class="h-9 px-3 text-xs" :disabled="page <= 1" @click="page--">Sebelumnya</Button>
            <template v-for="(p, i) in pages" :key="p ?? `gap-${i}`">
                <span v-if="p === null" class="px-1 text-gray-400">…</span>
                <Button
                    v-else
                    :variant="p === page ? 'primary' : 'ghost'"
                    class="h-9 w-9 px-0 text-xs"
                    :aria-current="p === page ? 'page' : undefined"
                    @click="page = p"
                >
                    {{ p }}
                </Button>
            </template>
            <Button variant="ghost" class="h-9 px-3 text-xs" :disabled="page >= pagination.last_page" @click="page++">Selanjutnya</Button>
        </nav>
    </div>
</template>
