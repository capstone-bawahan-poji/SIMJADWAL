import axios from 'axios';

/**
 * Client for /api/public/*: no session and no CSRF, so a 401 or 419 is not a reason to reload.
 */
export const publicApi = axios.create({
    baseURL: '/api/public',
    headers: {
        Accept: 'application/json',
        'X-Requested-With': 'XMLHttpRequest',
    },
});

publicApi.interceptors.request.use((config) => {
    if (config.params) {
        config.params = Object.fromEntries(
            Object.entries(config.params as Record<string, unknown>).filter(([, value]) => value !== null && value !== undefined && value !== ''),
        );
    }

    return config;
});
