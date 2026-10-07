<script setup lang="ts">
import AppLayout from '@/Layouts/AppLayout.vue';
import ConstraintPreview from '@/Components/constraint/ConstraintPreview.vue';
import SampleDataBadge from '@/Components/shared/SampleDataBadge.vue';
import { reviewSummary } from '@/mocks/constraint';
import { Head, Link, usePage } from '@inertiajs/vue3';
import { computed } from 'vue';

const programName = computed(() => usePage().props.auth.user.study_program?.name ?? '');
</script>

<template>
    <Head title="Review Constraint Prodi" />

    <AppLayout>
        <div class="p-6 md:p-8 max-w-7xl w-full mx-auto space-y-6">
            <div class="flex items-center gap-3">
                <h1 class="text-2xl font-bold text-gray-900 tracking-tight">Review &amp; Preview Constraint Prodi</h1>
                <SampleDataBadge />
            </div>

            <div class="grid grid-cols-1 gap-4 md:grid-cols-3">
                <div v-for="item in reviewSummary" :key="item.label" class="rounded-2xl border border-gray-100 bg-white p-5 shadow-sm">
                    <span class="text-[10px] font-extrabold uppercase tracking-wider text-gray-400">{{ item.label }}</span>
                    <div class="mt-1 text-lg font-bold text-gray-900">{{ item.value }}</div>
                    <p class="mt-1 text-[11px] text-gray-500">{{ item.hint }}</p>
                </div>
            </div>

            <ConstraintPreview :program-name="programName" />

            <div class="flex justify-end">
                <Link :href="route('prodi.constraints.index')" class="rounded-xl border border-gray-200 bg-white px-5 py-2.5 text-xs font-bold text-gray-700 shadow-sm hover:bg-gray-50">
                    Ubah Pengaturan
                </Link>
            </div>
        </div>
    </AppLayout>
</template>
