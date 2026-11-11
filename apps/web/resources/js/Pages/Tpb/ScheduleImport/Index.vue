<script setup lang="ts">
import TpbScheduleImport from '@/Components/scheduling/TpbScheduleImport.vue';
import Alert from '@/Components/ui/Alert.vue';
import { usePeriodPicker } from '@/composables/usePeriodPicker';
import AppLayout from '@/Layouts/AppLayout.vue';
import type { TpbImportResult } from '@/types/models';
import { Head, Link } from '@inertiajs/vue3';
import { ref } from 'vue';

/**
 * POS 1.2.c: the TPB schedule entered from a file when TPB does not run the GA. Every row must be a
 * TPB class already in Mapping Dosen. The result is a TPB run to activate and publish in Proses GA.
 */
const { periods, period, error: periodError } = usePeriodPicker();
const imported = ref<TpbImportResult | null>(null);
</script>

<template>
    <Head title="Import Jadwal TPB" />
    <AppLayout>
        <div class="mx-auto w-full max-w-5xl space-y-6 p-6 md:p-8">
            <header class="flex flex-wrap items-end justify-between gap-4">
                <div>
                    <h1 class="text-2xl font-bold tracking-tight text-gray-900">Import Jadwal TPB</h1>
                    <p class="mt-1 max-w-3xl text-sm text-gray-600">
                        Isi jadwal TPB dari file Excel tanpa menjalankan GA. Kelas dan dosen pengampu harus sudah ada di
                        <Link href="/tpb/mappings" class="font-medium text-primary hover:underline">Mapping Dosen</Link>.
                    </p>
                </div>
                <label class="text-sm font-medium text-gray-700">
                    Periode
                    <select v-model="period" class="mt-1 block min-w-56 rounded-lg border-gray-200 bg-white text-sm focus:border-primary focus:ring-primary">
                        <option v-for="item in periods" :key="item.id" :value="item.code">{{ item.label }}</option>
                    </select>
                </label>
            </header>

            <Alert v-if="periodError" :title="periodError.message" />
            <Alert v-if="imported" variant="success" :title="`Jadwal TPB tersimpan sebagai run #${imported.run_id}`" dismissible @dismiss="imported = null">
                Fakultas baru dapat memakainya setelah run ini diaktifkan dan dipublikasikan.
                <Link :href="`/tpb/ga?run=${imported.run_id}`" class="font-semibold text-primary hover:underline">Buka Proses GA</Link>
            </Alert>

            <TpbScheduleImport v-if="period" target="tpb" :period="period" @imported="imported = $event" />
        </div>
    </AppLayout>
</template>
