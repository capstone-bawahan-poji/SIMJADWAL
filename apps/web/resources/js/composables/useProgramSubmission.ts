import { onResourcesChanged } from '@/composables/useResourceChanges';
import { api, toApiError, type ApiError } from '@/lib/api';
import type { ProgramScheduleSubmission } from '@/types/models';
import { computed, ref, watch, type Ref } from 'vue';

export type SubmissionStep = 'submit' | 'withdraw' | 'return';

/**
 * Where one study program's schedule stands on its way to publication (POS steps 8-9), and the
 * steps that move it: submit and withdraw by the program, return by the faculty.
 * Reloads when the period or program changes, and when anyone changes a submission or a run.
 */
export function useProgramSubmission(studyProgramId: Ref<number | null>, period: Ref<string>) {
    const submission = ref<ProgramScheduleSubmission | null>(null);
    const loading = ref(false);
    const error = ref<ApiError | null>(null);
    const processing = ref<SubmissionStep | null>(null);

    const url = computed(() => `study-programs/${studyProgramId.value}/schedule-submission`);
    /** The program may still activate a run, move meetings and hand the schedule in. */
    const editable = computed(() => !submission.value?.faculty_published && ['draft', 'returned'].includes(submission.value?.status ?? 'draft'));
    const problemCount = computed(() => Object.values(submission.value?.problems ?? {}).reduce((sum, count) => sum + (count ?? 0), 0));

    async function reload(): Promise<void> {
        if (!studyProgramId.value || !period.value) return;
        loading.value = true;
        try {
            const response = await api.get<{ data: ProgramScheduleSubmission }>(url.value, { params: { period: period.value } });
            submission.value = response.data.data;
            error.value = null;
        } catch (e) {
            error.value = toApiError(e);
        } finally {
            loading.value = false;
        }
    }

    /** Resolves with the error, or null on success. return needs a note. */
    async function step(name: SubmissionStep, note?: string): Promise<ApiError | null> {
        processing.value = name;
        try {
            const response = await api.post<{ data: ProgramScheduleSubmission }>(`${url.value}/${name}`, note ? { note } : {}, { params: { period: period.value } });
            submission.value = response.data.data;

            return null;
        } catch (e) {
            return toApiError(e);
        } finally {
            processing.value = null;
        }
    }

    watch([studyProgramId, period], () => void reload(), { immediate: true });
    onResourcesChanged(['program_schedule_submissions', 'schedule_publications', 'scheduling_runs', 'schedule_details', 'room_slot_allocations'], () => void reload());

    return { submission, loading, error, processing, editable, problemCount, reload, step };
}
