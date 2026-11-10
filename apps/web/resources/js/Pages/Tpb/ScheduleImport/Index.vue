<script setup lang="ts">
import ExternalTpbScheduleImport from '@/Components/scheduling/ExternalTpbScheduleImport.vue';
import AppLayout from '@/Layouts/AppLayout.vue';
import { Head } from '@inertiajs/vue3';
</script>

<template>
    <Head title="Import Jadwal TPB" />
    <AppLayout>
        <div class="mx-auto w-full max-w-7xl space-y-6 p-6 md:p-8">
            <header>
                <h1 class="text-2xl font-bold tracking-tight text-gray-900">Import Jadwal TPB</h1>
                <p class="mt-1 text-sm text-gray-600">Unggah jadwal TPB dari Excel untuk melihat ketersediaan ruang dan sesi.</p>
            </header>
            <ExternalTpbScheduleImport />
        </div>
    </AppLayout>
</template>
