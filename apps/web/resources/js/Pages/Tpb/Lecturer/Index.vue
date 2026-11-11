<script setup lang="ts">
import AppLayout from '@/Layouts/AppLayout.vue';
import Alert from '@/Components/ui/Alert.vue';
import Panel from '@/Components/ui/Panel.vue';
import Pagination from '@/Components/ui/Pagination.vue';
import { useCollection, usePaginatedList } from '@/composables/useApiList';
import { queryParam } from '@/lib/query';
import type { FacultySummary, Lecturer, StudyProgramSummary } from '@/types/models';
import { Head } from '@inertiajs/vue3';
import { computed } from 'vue';

// Every lecturer of the institute, read only: name and home study program.
const controlClass = 'h-10 rounded-lg border-gray-200 bg-white text-sm text-gray-700 focus:border-primary focus:ring-primary';
const { filters, items: lecturers, pagination, page, loading, error } = usePaginatedList<Lecturer, { q: string; faculty_id: number | ''; study_program_id: number | '' }>(
    'lecturers',
    { q: queryParam('q'), faculty_id: '', study_program_id: '' },
    20,
);
// Faculties come as a plain collection, not paginated.
const { items: faculties, error: facultyError } = useCollection<FacultySummary>('faculties');
const { items: programs, error: programError } = useCollection<StudyProgramSummary>('study-programs');
const programsOfFaculty = computed(() => programs.value.filter((p) => !filters.faculty_id || p.faculty_id === filters.faculty_id));

function fullName(lecturer: Lecturer): string {
    return lecturer.title ? `${lecturer.name}, ${lecturer.title}` : lecturer.name;
}

</script>

<template>
    <Head title="Kelola Dosen" />
    <AppLayout>
        <div class="space-y-6">
            <header>
                <h1 class="text-2xl font-bold tracking-tight text-gray-900">Kelola Dosen</h1>
                <p class="mt-1 text-sm text-gray-500">Daftar seluruh dosen institut, hanya untuk dilihat. Data dosen diubah oleh masing-masing program studi.</p>
            </header>
            <Alert v-if="error" :title="error.message" />
            <Alert v-if="programError" :title="programError.message" />
            <Alert v-if="facultyError" :title="facultyError.message" />
            <Panel>
                <div class="flex flex-col gap-3 border-b border-gray-200 p-4 lg:flex-row lg:items-center">
                    <input v-model="filters.q" type="search" placeholder="Cari nama atau NIP" aria-label="Cari dosen" :class="[controlClass, 'w-full lg:w-72']" />
                    <select v-model="filters.faculty_id" :class="[controlClass, 'lg:w-64']" aria-label="Filter fakultas" @change="filters.study_program_id = ''">
                        <option value="">Semua fakultas</option>
                        <option v-for="faculty in faculties" :key="faculty.id" :value="faculty.id">{{ faculty.name }}</option>
                    </select>
                    <select v-model="filters.study_program_id" :class="[controlClass, 'lg:w-64']" aria-label="Filter program studi">
                        <option value="">Semua program studi</option>
                        <option v-for="program in programsOfFaculty" :key="program.id" :value="program.id">{{ program.name }}</option>
                    </select>
                    <p class="text-sm text-gray-500 lg:ml-auto"><span class="font-semibold tabular-nums text-gray-900">{{ pagination.total }}</span> dosen {{ filters.q || filters.faculty_id || filters.study_program_id ? 'ditemukan' : 'terdaftar' }}</p>
                </div>
                <div class="overflow-x-auto">
                    <table class="w-full border-collapse text-left text-sm">
                        <thead>
                            <tr class="border-b border-gray-200 bg-gray-50 text-xs font-semibold uppercase tracking-wide text-gray-500">
                                <th class="px-6 py-3">Dosen</th>
                                <th class="px-6 py-3">Program Studi Asal</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-100" :class="{ 'opacity-60': loading }">
                            <tr v-if="!loading && lecturers.length === 0"><td colspan="2" class="px-6 py-10 text-center text-gray-500">Tidak ada dosen yang cocok dengan pencarian atau filter ini.</td></tr>
                            <tr v-for="lecturer in lecturers" :key="lecturer.id" class="hover:bg-gray-50">
                                <td class="px-6 py-3.5"><span class="font-medium text-gray-900">{{ fullName(lecturer) }}</span><span class="block text-xs tabular-nums text-gray-500">{{ lecturer.nip }}</span></td>
                                <td class="px-6 py-3.5 text-gray-700">{{ lecturer.study_program.name }}</td>
                            </tr>
                        </tbody>
                    </table>
                </div>
                <Pagination v-model:page="page" :pagination="pagination" label="dosen" />
            </Panel>
        </div>
    </AppLayout>
</template>
