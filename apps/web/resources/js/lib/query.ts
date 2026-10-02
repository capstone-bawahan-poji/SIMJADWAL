import { usePage } from '@inertiajs/vue3';

/**
 * Query string value of the current Inertia page, e.g. ?q=E101 from the topbar search.
 * Read from page.url, which is already updated when the new page component is set up.
 */
export function queryParam(name: string): string {
    return new URL(usePage().url, window.location.origin).searchParams.get(name) ?? '';
}
