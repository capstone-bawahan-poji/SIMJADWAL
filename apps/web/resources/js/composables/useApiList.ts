import { api, toApiError, type ApiError } from '@/lib/api';
import type { Collection, Paginated, Pagination } from '@/types/models';
import { onMounted, reactive, ref, shallowRef, watch, type Ref } from 'vue';

export function usePaginatedList<T, F extends Record<string, unknown>>(url: string, initialFilters: F, perPage = 20) {
    const filters = reactive({ ...initialFilters }) as F;
    const items = shallowRef<T[]>([]);
    const pagination = ref<Pagination>({ page: 1, per_page: perPage, total: 0, last_page: 1 });
    const page = ref(1);
    const loading = ref(false);
    const error = ref<ApiError | null>(null);
    let requestId = 0;

    async function load(): Promise<void> {
        const id = ++requestId;
        loading.value = true;

        try {
            const response = await api.get<Paginated<T>>(url, { params: { ...filters, page: page.value, per_page: perPage } });

            if (id === requestId) {
                items.value = response.data.data;
                pagination.value = response.data.meta.pagination;
                error.value = null;
            }
        } catch (e) {
            if (id === requestId) {
                error.value = toApiError(e);
            }
        } finally {
            if (id === requestId) {
                loading.value = false;
            }
        }
    }

    let searchTimer: ReturnType<typeof setTimeout> | undefined;

    watch(
        () => ({ ...filters }),
        (current, previous) => {
            clearTimeout(searchTimer);
            const onlySearchChanged = Object.keys(current).every((key) => key === 'q' || current[key] === previous[key]);

            searchTimer = setTimeout(
                () => {
                    if (page.value === 1) {
                        void load();
                    } else {
                        page.value = 1;
                    }
                },
                onlySearchChanged ? 300 : 0,
            );
        },
    );

    watch(page, () => void load());
    onMounted(load);

    return { filters, items, pagination, page, loading, error, reload: load };
}

export function useCollection<T>(url: string, params?: Ref<Record<string, unknown>>) {
    const items = shallowRef<T[]>([]);
    const loading = ref(false);
    const error = ref<ApiError | null>(null);

    async function load(): Promise<void> {
        loading.value = true;

        try {
            const response = await api.get<Collection<T>>(url, { params: params?.value });
            items.value = response.data.data;
            error.value = null;
        } catch (e) {
            error.value = toApiError(e);
        } finally {
            loading.value = false;
        }
    }

    if (params) {
        watch(params, () => void load(), { deep: true });
    }

    onMounted(load);

    return { items, loading, error, reload: load };
}

export async function countOf(url: string, params: Record<string, unknown> = {}): Promise<number> {
    const response = await api.get<Paginated<unknown>>(url, { params: { ...params, per_page: 1 } });

    return response.data.meta.pagination.total;
}
