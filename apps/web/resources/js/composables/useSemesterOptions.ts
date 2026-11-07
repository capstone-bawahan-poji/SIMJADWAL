import { computed, toValue, watch, type ComputedRef, type MaybeRefOrGetter, type Ref } from 'vue';

const ALL_SEMESTERS = [1, 2, 3, 4, 5, 6, 7, 8];

/**
 * Semesters scheduled in a period code (YYYY/YYYY-GANJIL|GENAP); mirrors App\Services\Scheduling\Period::semesters().
 * An empty code (period not known yet) yields 1–8.
 */
export function periodSemesters(period: string): number[] {
    if (!period) return ALL_SEMESTERS;

    return period.endsWith('-GANJIL') ? [1, 3, 5, 7] : [2, 4, 6, 8];
}

/**
 * Semester options for the given period code. When the period changes and the selected semester
 * is not among the new options, semester is reset to '' (all semesters).
 */
export function useSemesterOptions(period: MaybeRefOrGetter<string>, semester: Ref<string>): ComputedRef<number[]> {
    const options = computed(() => periodSemesters(toValue(period)));

    watch(
        options,
        (values) => {
            if (semester.value && !values.includes(Number(semester.value))) semester.value = '';
        },
        // sync: reset in the same change as the period, so dependent watchers fire once.
        { immediate: true, flush: 'sync' },
    );

    return options;
}
