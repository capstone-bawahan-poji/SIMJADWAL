import { useCollection } from '@/composables/useApiList';
import { queryParam } from '@/lib/query';
import type { AcademicPeriod } from '@/types/models';
import { ref, watch } from 'vue';

export function usePeriodPicker() {
    const { items: periods, error } = useCollection<AcademicPeriod>('academic-periods');
    const period = ref(queryParam('period'));

    watch(periods, (items) => {
        if (!period.value && items.length) period.value = items.find((item) => item.is_current)?.code ?? items[0].code;
    }, { immediate: true });

    return { periods, period, error };
}
