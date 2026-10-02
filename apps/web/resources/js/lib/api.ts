import axios, { AxiosError } from 'axios';

/**
 * Client for /api/internal/* (session + CSRF). axios sends the XSRF-TOKEN cookie back as
 * X-XSRF-TOKEN on same-origin requests, so no token handling is needed here.
 */
export const api = axios.create({
    baseURL: '/api/internal',
    headers: {
        Accept: 'application/json',
        'X-Requested-With': 'XMLHttpRequest',
    },
});

// Laravel's `boolean` rule accepts 1/0 but not "true"/"false"; empty filters are left out.
api.interceptors.request.use((config) => {
    if (config.params) {
        config.params = Object.fromEntries(
            Object.entries(config.params as Record<string, unknown>)
                .filter(([, value]) => value !== null && value !== undefined && value !== '')
                .map(([key, value]) => [key, typeof value === 'boolean' ? Number(value) : value]),
        );
    }

    return config;
});

// 401: signed out elsewhere. 419: the session expired. A reload lets the server redirect to login.
api.interceptors.response.use(undefined, (error: AxiosError) => {
    if (error.response?.status === 401 || error.response?.status === 419) {
        window.location.reload();
    }

    return Promise.reject(error);
});

/** Error body of the API contract: {"message", "code", "errors"}. */
export interface ApiError {
    status: number;
    message: string;
    code: string;
    errors: Record<string, unknown>;
}

export function toApiError(error: unknown): ApiError {
    if (error instanceof AxiosError && error.response) {
        const body = (error.response.data ?? {}) as Partial<ApiError>;

        return {
            status: error.response.status,
            message: body.message ?? 'Terjadi kesalahan pada server.',
            code: body.code ?? 'SERVER_ERROR',
            errors: body.errors ?? {},
        };
    }

    return { status: 0, message: 'Tidak dapat terhubung ke server.', code: 'NETWORK_ERROR', errors: {} };
}

/** First message per field of a 422 VALIDATION_FAILED error. */
export function fieldErrors(error: ApiError): Record<string, string> {
    if (error.code !== 'VALIDATION_FAILED') {
        return {};
    }

    return Object.fromEntries(
        Object.entries(error.errors).map(([field, messages]) => [field, Array.isArray(messages) ? String(messages[0]) : String(messages)]),
    );
}

/** DELETE a resource. Resolves with the error (e.g. 409 RESOURCE_IN_USE), or null on success. */
export async function destroy(url: string): Promise<ApiError | null> {
    try {
        await api.delete(url);

        return null;
    } catch (e) {
        return toApiError(e);
    }
}
