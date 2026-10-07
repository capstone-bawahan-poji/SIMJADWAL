import { api, toApiError, type ApiError } from '@/lib/api';
import type { ConstraintSubmissionDetail, ConstraintType, Collection, Single, TimeSlot } from '@/types/models';
import { ref } from 'vue';

/** Detail of one program plus the reference data ConstraintPreview needs. */
export function useConstraintDetail() {
    const detail = ref<ConstraintSubmissionDetail | null>(null);
    const constraintTypes = ref<ConstraintType[]>([]);
    const timeSlots = ref<TimeSlot[]>([]);
    const loading = ref(false);
    const error = ref<ApiError | null>(null);

    async function load(studyProgramId: number): Promise<void> {
        loading.value = true;

        try {
            const [d, types, slots] = await Promise.all([
                api.get<Single<ConstraintSubmissionDetail>>(`constraint-submissions/${studyProgramId}`),
                api.get<Collection<ConstraintType>>('constraint-types'),
                api.get<Collection<TimeSlot>>('time-slots'),
            ]);
            detail.value = d.data.data;
            constraintTypes.value = types.data.data;
            timeSlots.value = slots.data.data;
            error.value = null;
        } catch (e) {
            error.value = toApiError(e);
        } finally {
            loading.value = false;
        }
    }

    return { detail, constraintTypes, timeSlots, loading, error, load };
}
