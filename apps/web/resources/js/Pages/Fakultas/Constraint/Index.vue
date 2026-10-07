<script setup lang="ts">
import AppLayout from '@/Layouts/AppLayout.vue';
import ConstraintPreview from '@/Components/constraint/ConstraintPreview.vue';
import SampleDataBadge from '@/Components/shared/SampleDataBadge.vue';
import { programStatuses } from '@/mocks/constraint';
import { Head } from '@inertiajs/vue3';
import { computed, ref } from 'vue';

const selectedId = ref(programStatuses[0].id);
const selected = computed(() => programStatuses.find((p) => p.id === selectedId.value) ?? programStatuses[0]);
const incomplete = computed(() => programStatuses.filter((p) => p.state === 'warning'));
</script>

<template>
    <Head title="Kelola Constraint" />

    <AppLayout>
        <div class="p-6 md:p-8 max-w-7xl w-full mx-auto space-y-6">
            <div class="flex items-center gap-3">
                <h1 class="text-2xl font-bold text-gray-900 tracking-tight">Preview Constraint per Prodi</h1>
                <SampleDataBadge />
            </div>

            <div class="space-y-3 rounded-2xl border border-gray-100 bg-white p-5 shadow-sm">
                <div class="flex flex-wrap items-center gap-2">
                    <span class="text-xs font-semibold text-gray-500">Program Studi:</span>
                    <button
                        v-for="program in programStatuses"
                        :key="program.id"
                        type="button"
                        class="rounded-full border px-3 py-1 text-xs font-semibold transition-colors"
                        :class="program.id === selectedId ? 'border-primary bg-primary text-white' : 'border-gray-200 bg-gray-50 text-gray-600 hover:bg-gray-100'"
                        @click="selectedId = program.id"
                    >
                        {{ program.name }}
                    </button>
                </div>
                <p v-for="program in incomplete" :key="program.id" class="rounded-xl bg-amber-50 px-3 py-2 text-xs text-amber-700">
                    {{ program.name }} masih memiliki preferensi dosen yang belum terisi lengkap.
                </p>
            </div>

            <ConstraintPreview :program-name="selected.name" />
        </div>
    </AppLayout>
</template>
