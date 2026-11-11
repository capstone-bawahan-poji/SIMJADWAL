import { onResourcesChanged } from '@/composables/useResourceChanges';
import { api, toApiError, type ApiError } from '@/lib/api';
import type { ConstraintSubmission, Paginated } from '@/types/models';
import { onMounted, ref } from 'vue';

/**
 * Constraint status of the signed-in prodi admin's program. The API scopes the list to
 * that one program, so per_page 1 is enough.
 */
export function useOwnSubmission() {
    const submission = ref<ConstraintSubmission | null>(null);
    const error = ref<ApiError | null>(null);

    async function reload(): Promise<void> {
        try {
            const response = await api.get<Paginated<ConstraintSubmission>>('constraint-submissions', { params: { per_page: 1 } });
            submission.value = response.data.data[0] ?? null;
            error.value = null;
        } catch (e) {
            error.value = toApiError(e);
        }
    }

    onMounted(reload);
    onResourcesChanged(['study_programs'], () => void reload());

    return { submission, error, reload };
}