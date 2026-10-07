<script setup lang="ts">
import AppLayout from '@/Layouts/AppLayout.vue';
import ConstraintPreview from '@/Components/constraint/ConstraintPreview.vue';
import ProgramLockBanner from '@/Components/constraint/ProgramLockBanner.vue';
import Alert from '@/Components/ui/Alert.vue';
import Button from '@/Components/ui/Button.vue';
import ConfirmDialog from '@/Components/ui/ConfirmDialog.vue';
import { useConstraintDetail } from '@/composables/useConstraintDetail';
import { runSubmissionAction } from '@/lib/constraintSubmission';
import { useOwnSubmission } from '@/composables/useOwnSubmission';
import { Head, Link } from '@inertiajs/vue3';
import { ref, watch } from 'vue';

const { submission, locked, reload } = useOwnSubmission();
const { detail, constraintTypes, timeSlots, error, load } = useConstraintDetail();

watch(submission, (row, previous) => {
    if (row && !previous) void load(row.id);
}, { immediate: true });

const confirming = ref(false);
const processing = ref(false);
const failure = ref<string | null>(null);
const missingCourses = ref<string[]>([]);
const flash = ref<string | null>(null);

async function submit(): Promise<void> {
    if (!submission.value) return;

    processing.value = true;
    failure.value = null;
    missingCourses.value = [];
    const result = await runSubmissionAction(submission.value.id, 'submit');
    processing.value = false;

    if ('error' in result) {
        confirming.value = false;
        failure.value = result.error.message;
        missingCourses.value = result.courses;

        return;
    }

    confirming.value = false;
    flash.value = 'Constraint berhasil di-submit ke fakultas.';
    await reload();
    await load(result.row.id);
}
</script>

<template>
    <Head title="Review Constraint Prodi" />

    <AppLayout>
        <div class="p-6 md:p-8 max-w-7xl w-full mx-auto space-y-6">
            <div>
                <h1 class="text-2xl font-bold text-gray-900 tracking-tight">Review &amp; Submit Constraint</h1>
                <p class="mt-1 text-sm text-gray-500">Periksa ringkasan, lalu submit ke fakultas. Setelah submit, data prodi terkunci sampai fakultas mengembalikannya.</p>
            </div>

            <ProgramLockBanner :submission="submission" />
            <Alert v-if="flash" variant="success" :title="flash" dismissible @dismiss="flash = null" />
            <Alert v-if="error" :title="error.message" />
            <div v-if="failure" class="rounded-xl border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-800" role="alert">
                <p class="font-semibold">{{ failure }}</p>
                <ul v-if="missingCourses.length" class="mt-2 list-disc space-y-1 pl-5 text-xs">
                    <li v-for="line in missingCourses" :key="line">{{ line }}</li>
                </ul>
            </div>

            <ConstraintPreview v-if="detail" :detail="detail" :constraint-types="constraintTypes" :time-slots="timeSlots" />

            <div class="flex flex-col-reverse gap-3 sm:flex-row sm:justify-end">
                <Link :href="route('prodi.constraints.index')" class="rounded-xl border border-gray-200 bg-white px-5 py-2.5 text-center text-xs font-bold text-gray-700 shadow-sm hover:bg-gray-50">
                    Ubah Preferensi
                </Link>
                <Button type="button" :disabled="locked || !submission?.can_submit" @click="confirming = true">Submit Constraint</Button>
            </div>
        </div>

        <ConfirmDialog :show="confirming" title="Submit constraint?" confirm-label="Submit" :danger="false" :processing="processing" :error="failure" @confirm="submit" @close="confirming = false">
            Data mata kuliah, dosen, mapping, dan preferensi akan terkunci dan dikirim ke fakultas untuk direview.
        </ConfirmDialog>
    </AppLayout>
</template>
