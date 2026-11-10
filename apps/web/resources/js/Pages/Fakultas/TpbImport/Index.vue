<script setup lang="ts">
import ExternalTpbScheduleImport from '@/Components/scheduling/ExternalTpbScheduleImport.vue';
import AppLayout from '@/Layouts/AppLayout.vue';
import { Head, usePage } from '@inertiajs/vue3';
import { computed } from 'vue';

const page = usePage();
const facultyName = computed(() => page.props.auth.user.faculty?.name ?? 'Fakultas');
</script>

<template>
    <Head title="Import Jadwal TPB" />
    <AppLayout>
        <div class="mx-auto w-full max-w-7xl space-y-6 p-6 md:p-8">
            <header class="flex flex-wrap items-start justify-between gap-4">
                <div>
                    <h1 class="text-2xl font-bold tracking-tight text-gray-900">Import Jadwal TPB</h1>
                    <p class="mt-1 text-sm text-gray-600">Unggah jadwal TPB eksternal untuk melihat ruang yang masih bisa dialokasikan ke prodi {{ facultyName }}.</p>
                </div>
            </header>
            <ExternalTpbScheduleImport />
        </div>
    </AppLayout>
</template>
