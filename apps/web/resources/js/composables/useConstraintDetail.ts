import { onResourcesChanged } from '@/composables/useResourceChanges';
import { api, toApiError, type ApiError } from '@/lib/api';
import type { ConstraintSubmissionDetail, ConstraintType, Collection, Single, TimeSlot } from '@/types/models';
import { ref } from 'vue';

export function useConstraintDetail() {
    const detail = ref<ConstraintSubmissionDetail | null>(null);
    const constraintTypes = ref<ConstraintType[]>([]);
    const timeSlots = ref<TimeSlot[]>([]);
    const loading = ref(false);
    const error = ref<ApiError | null>(null);
    let loadedId: number | null = null;

    async function load(studyProgramId: number): Promise<void> {
        loadedId = studyProgramId;
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

    onResourcesChanged(['study_programs', 'lecturer_preferences', 'course_lecturers', 'constraint_types', 'time_slots'], () => {
        if (loadedId !== null) {
            void load(loadedId);
        }
    });

    return { detail, constraintTypes, timeSlots, loading, error, load };
}