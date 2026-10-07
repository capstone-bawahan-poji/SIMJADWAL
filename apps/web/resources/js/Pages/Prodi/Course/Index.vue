<script setup lang="ts">
import AppLayout from '@/Layouts/AppLayout.vue';
import Alert from '@/Components/ui/Alert.vue';
import Card from '@/Components/ui/Card.vue';
import Pagination from '@/Components/ui/Pagination.vue';
import { usePaginatedList } from '@/composables/useApiList';
import { queryParam } from '@/lib/query';
import type { Course } from '@/types/models';
import { Head } from '@inertiajs/vue3';

const { filters, items: courses, pagination, page, loading, error } = usePaginatedList<Course, { q: string; semester: number | '' }>(
    'courses',
    { q: queryParam('q'), semester: '' },
);

const semesters = [1, 2, 3, 4, 5, 6, 7, 8];
</script>

<template>
    <Head title="Data Mata Kuliah" />

    <AppLayout>
        <div class="p-6 md:p-8 max-w-7xl w-full mx-auto space-y-6">
            <div>
                <h1 class="text-2xl font-bold text-gray-900 tracking-tight">Data Mata Kuliah</h1>
                <p class="mt-1 text-sm text-gray-500">Mata kuliah program studi Anda beserta kelas paralel dan kelengkapan dosen pengampu.</p>
            </div>

            <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                <div class="rounded-2xl border border-gray-100 bg-white p-5 shadow-sm">
                    <span class="text-[10px] font-extrabold uppercase tracking-wider text-gray-400">Total Mata Kuliah</span>
                    <div class="mt-1 text-3xl font-bold text-gray-900">{{ pagination.total }}</div>
                </div>
            </div>

            <Card class="!p-0 border border-gray-100 shadow-sm">
                <div class="flex flex-col gap-3 border-b border-gray-100 p-5 md:flex-row md:items-center md:justify-between">
                    <select v-model="filters.semester" class="w-44 rounded-xl border-gray-200 bg-gray-50 text-xs font-semibold text-gray-700 focus:border-primary focus:ring-primary" aria-label="Filter semester">
                        <option value="">Semua Semester</option>
                        <option v-for="s in semesters" :key="s" :value="s">Semester {{ s }}</option>
                    </select>
                    <input v-model="filters.q" type="search" placeholder="Cari kode atau nama mata kuliah..." aria-label="Cari mata kuliah" class="w-full rounded-xl border-gray-200 bg-gray-50 text-xs placeholder-gray-400 focus:border-primary focus:ring-primary md:w-72" />
                </div>

                <Alert v-if="error" :title="error.message" class="m-5" />

                <div class="w-full overflow-x-auto">
                    <table class="w-full border-collapse text-left">
                        <thead>
                            <tr class="border-b border-gray-100 bg-gray-50/70 text-[11px] font-bold uppercase tracking-wider text-gray-500">
                                <th class="px-6 py-4">Kode &amp; Mata Kuliah</th>
                                <th class="px-6 py-4 text-center">SKS</th>
                                <th class="px-6 py-4 text-center">Semester</th>
                                <th class="px-6 py-4">Kelas Paralel</th>
                                <th class="px-6 py-4">Sifat</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-100 text-xs text-gray-700" :class="{ 'opacity-60': loading }">
                            <tr v-if="!loading && courses.length === 0">
                                <td colspan="5" class="px-6 py-10 text-center text-sm text-gray-500">Tidak ada mata kuliah yang cocok dengan filter.</td>
                            </tr>
                            <tr v-for="course in courses" :key="course.id" class="transition-colors hover:bg-gray-50/60">
                                <td class="px-6 py-4">
                                    <div class="flex items-start gap-3">
                                        <span class="mt-0.5 rounded border border-blue-100 bg-blue-50 px-2 py-0.5 font-mono text-[10px] font-bold text-blue-700">{{ course.code }}</span>
                                        <span class="font-bold text-gray-900">{{ course.name }}</span>
                                    </div>
                                </td>
                                <td class="px-6 py-4 text-center font-semibold">{{ course.sks }}</td>
                                <td class="px-6 py-4 text-center text-gray-500">{{ course.semester }}</td>
                                <td class="px-6 py-4">
                                    <span class="font-semibold" :class="course.course_lecturers_count < course.parallel_class_count ? 'text-red-600' : 'text-gray-700'">
                                        {{ course.course_lecturers_count }} / {{ course.parallel_class_count }} kelas berdosen
                                    </span>
                                    <span class="block text-[11px] text-gray-400">Kapasitas {{ course.class_capacity }} mahasiswa/kelas</span>
                                </td>
                                <td class="px-6 py-4 font-semibold">{{ course.is_tpb ? 'TPB / MKWU' : 'Prodi' }}</td>
                            </tr>
                        </tbody>
                    </table>
                </div>

                <Pagination v-model:page="page" :pagination="pagination" label="mata kuliah" />
            </Card>
        </div>
    </AppLayout>
</template>
