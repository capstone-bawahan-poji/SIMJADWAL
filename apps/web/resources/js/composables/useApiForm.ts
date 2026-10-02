import { api, fieldErrors, toApiError, type ApiError } from '@/lib/api';
import type { Single } from '@/types/models';
import { reactive, ref } from 'vue';

type Method = 'post' | 'patch';

export function useApiForm<D extends Record<string, unknown>>(defaults: () => D) {
    const data = reactive(defaults()) as D;
    const errors = ref<Record<string, string>>({});
    const failure = ref<ApiError | null>(null);
    const processing = ref(false);

    function reset(values?: Partial<D>): void {
        Object.assign(data, defaults(), values ?? {});
        errors.value = {};
        failure.value = null;
    }

    async function submit<R>(method: Method, url: string, payload: Record<string, unknown> = { ...data }): Promise<R | null> {
        processing.value = true;
        errors.value = {};
        failure.value = null;

        try {
            const response = await api.request<Single<R>>({ method, url, data: payload });

            return response.data.data;
        } catch (e) {
            const error = toApiError(e);
            errors.value = fieldErrors(error);
            failure.value = Object.keys(errors.value).length ? null : error;

            return null;
        } finally {
            processing.value = false;
        }
    }

    return { data, errors, failure, processing, reset, submit };
}
