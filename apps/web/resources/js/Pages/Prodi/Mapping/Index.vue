<script setup lang="ts">
import AppLayout from '@/Layouts/AppLayout.vue';
import Alert from '@/Components/ui/Alert.vue';
import Card from '@/Components/ui/Card.vue';
import Pagination from '@/Components/ui/Pagination.vue';
import { usePaginatedList } from '@/composables/useApiList';
import type { TeachingAssignment } from '@/types/models';
import { Head } from '@inertiajs/vue3';

const { items: assignments, pagination, page, loading, error } = usePaginatedList<TeachingAssignment, Record<string, never>>('teaching-assignments', {});
</script>

<template>
    <Head title="Mapping Dosen ke Mata Kuliah" />

    <AppLayout>
        <div class="p-6 md:p-8 max-w-7xl w-full mx-auto space-y-6">
            <div>
                <h1 class="text-2xl font-bold text-gray-900 tracking-tight">Mapping Dosen ke Mata Kuliah</h1>
                <p class="mt-1 text-sm text-gray-500">Dosen pengampu tiap kelas mata kuliah program studi Anda.</p>
            </div>

            <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                <div class="rounded-2xl border border-gray-100 bg-white p-5 shadow-sm">
                    <span class="text-[10px] font-extrabold uppercase tracking-wider text-gray-400">Kelas Terpetakan</span>
                    <div class="mt-1 text-3xl font-bold text-gray-900">{{ pagination.total }}</div>
                </div>
            </div>

            <Card class="!p-0 border border-gray-100 shadow-sm">
                <Alert v-if="error" :title="error.message" class="m-5" />

                <div class="w-full overflow-x-auto">
                    <table class="w-full border-collapse text-left">
                        <thead>
                            <tr class="border-b border-gray-100 bg-gray-50/70 text-[11px] font-bold uppercase tracking-wider text-gray-500">
                                <th class="px-6 py-4">Mata Kuliah</th>
                                <th class="px-6 py-4 text-center">Kelas</th>
                                <th class="px-6 py-4">Dosen Pengampu</th>
                                <th class="px-6 py-4">Ruang</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-100 text-xs text-gray-700" :class="{ 'opacity-60': loading }">
                            <tr v-if="!loading && assignments.length === 0">
                                <td colspan="4" class="px-6 py-10 text-center text-sm text-gray-500">Belum ada dosen yang dipetakan.</td>
                            </tr>
                            <tr v-for="assignment in assignments" :key="assignment.id" class="transition-colors hover:bg-gray-50/60">
                                <td class="px-6 py-4">
                                    <span class="mr-2 rounded border border-blue-100 bg-blue-50 px-2 py-0.5 font-mono text-[10px] font-bold text-blue-700">{{ assignment.course.code }}</span>
                                    <span class="font-bold text-gray-900">{{ assignment.course.name }}</span>
                                    <span class="block text-[11px] text-gray-400">{{ assignment.course.sks }} SKS • Semester {{ assignment.course.semester }}</span>
                                </td>
                                <td class="px-6 py-4 text-center font-semibold">{{ assignment.class_label }}</td>
                                <td class="px-6 py-4 font-semibold">{{ assignment.lecturer.name }}</td>
                                <td class="px-6 py-4">{{ assignment.room?.name ?? assignment.room?.code ?? '-' }}</td>
                            </tr>
                        </tbody>
                    </table>
                </div>

                <Pagination v-model:page="page" :pagination="pagination" label="kelas" />
            </Card>
        </div>
    </AppLayout>
</template>
