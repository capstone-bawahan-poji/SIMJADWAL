<script setup lang="ts">
import TpbScheduleImport from '@/Components/scheduling/TpbScheduleImport.vue';
import Alert from '@/Components/ui/Alert.vue';
import { usePeriodPicker } from '@/composables/usePeriodPicker';
import AppLayout from '@/Layouts/AppLayout.vue';
import type { TpbImportResult } from '@/types/models';
import { Head, Link } from '@inertiajs/vue3';
import { ref } from 'vue';

/**
 * POS 1.2.c: the TPB schedule entered from a file when TPB does not run the GA. One file format for
 * data and schedule (docs/plans/konvensi-file-tpb.md). The schedule becomes a TPB run to activate and
 * publish in Proses GA; a file without schedule cells only saves the groups and classes.
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
                        Isi grup, kelas, dan jadwal TPB dari satu file Excel tanpa menjalankan GA. File tanpa kolom Grup dan Peserta
                        hanya memuat jadwal, dan kelasnya harus sudah ada di
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
            <Alert v-if="imported && imported.run_id" variant="success" :title="`Jadwal TPB tersimpan sebagai run #${imported.run_id}`" dismissible @dismiss="imported = null">
                Fakultas baru dapat memakainya setelah run ini diaktifkan dan dipublikasikan.
                <Link :href="`/tpb/ga?run=${imported.run_id}`" class="font-semibold text-primary hover:underline">Buka Proses GA</Link>
            </Alert>
            <Alert v-else-if="imported" variant="success" title="Data TPB tersimpan" dismissible @dismiss="imported = null">
                {{ imported.data?.groups ?? 0 }} grup dan {{ imported.data?.classes ?? 0 }} kelas tersimpan. File ini tidak memuat jadwal, jadi tidak ada run baru.
            </Alert>

            <TpbScheduleImport v-if="period" target="tpb" :period="period" @imported="imported = $event" />
        </div>
    </AppLayout>
</template>
