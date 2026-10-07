<script setup lang="ts">
import AppLayout from '@/Layouts/AppLayout.vue';
import Alert from '@/Components/ui/Alert.vue';
import Card from '@/Components/ui/Card.vue';
import Pagination from '@/Components/ui/Pagination.vue';
import { usePaginatedList } from '@/composables/useApiList';
import { initials } from '@/lib/labels';
import { queryParam } from '@/lib/query';
import type { Lecturer } from '@/types/models';
import { Head } from '@inertiajs/vue3';

const { filters, items: lecturers, pagination, page, loading, error } = usePaginatedList<Lecturer, { q: string }>('lecturers', { q: queryParam('q') });
</script>

<template>
    <Head title="Data Dosen" />

    <AppLayout>
        <div class="p-6 md:p-8 max-w-7xl w-full mx-auto space-y-6">
            <div>
                <h1 class="text-2xl font-bold text-gray-900 tracking-tight">Data Dosen</h1>
                <p class="mt-1 text-sm text-gray-500">Dosen program studi Anda dan jumlah kelas yang diampu.</p>
            </div>

            <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                <div class="rounded-2xl border border-gray-100 bg-white p-5 shadow-sm">
                    <span class="text-[10px] font-extrabold uppercase tracking-wider text-gray-400">Total Dosen Prodi</span>
                    <div class="mt-1 text-3xl font-bold text-gray-900">{{ pagination.total }}</div>
                </div>
            </div>

            <Card class="!p-0 border border-gray-100 shadow-sm">
                <div class="flex justify-end border-b border-gray-100 p-5">
                    <input v-model="filters.q" type="search" placeholder="Cari nama atau NIP dosen..." aria-label="Cari dosen" class="w-full rounded-xl border-gray-200 bg-gray-50 text-xs placeholder-gray-400 focus:border-primary focus:ring-primary md:w-72" />
                </div>

                <Alert v-if="error" :title="error.message" class="m-5" />

                <div class="w-full overflow-x-auto">
                    <table class="w-full border-collapse text-left">
                        <thead>
                            <tr class="border-b border-gray-100 bg-gray-50/70 text-[11px] font-bold uppercase tracking-wider text-gray-500">
                                <th class="px-6 py-4">Nama Dosen</th>
                                <th class="px-6 py-4">Gelar</th>
                                <th class="px-6 py-4">NIP</th>
                                <th class="px-6 py-4 text-center">Kelas Diampu</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-100 text-xs text-gray-700" :class="{ 'opacity-60': loading }">
                            <tr v-if="!loading && lecturers.length === 0">
                                <td colspan="4" class="px-6 py-10 text-center text-sm text-gray-500">Tidak ada dosen yang cocok dengan pencarian.</td>
                            </tr>
                            <tr v-for="lecturer in lecturers" :key="lecturer.id" class="transition-colors hover:bg-gray-50/60">
                                <td class="px-6 py-4">
                                    <div class="flex items-center gap-3">
                                        <div class="flex h-9 w-9 shrink-0 items-center justify-center rounded-lg bg-primary/10 text-[10px] font-bold text-primary">{{ initials(lecturer.name) }}</div>
                                        <span class="font-bold text-gray-900">{{ lecturer.name }}</span>
                                    </div>
                                </td>
                                <td class="px-6 py-4">{{ lecturer.title ?? '-' }}</td>
                                <td class="px-6 py-4 font-mono">{{ lecturer.nip }}</td>
                                <td class="px-6 py-4 text-center font-semibold">{{ lecturer.course_lecturers_count }}</td>
                            </tr>
                        </tbody>
                    </table>
                </div>

                <Pagination v-model:page="page" :pagination="pagination" label="dosen" />
            </Card>
        </div>
    </AppLayout>
</template>
