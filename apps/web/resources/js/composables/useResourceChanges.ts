import { listenForChanges, type ChangedResources } from '@/lib/echo';
import { onMounted, onUnmounted } from 'vue';

/** API paths whose rows live in a table of another name. */
const TABLE_OF_PATH: Record<string, string> = {
    'teaching-assignments': 'course_lecturers',
    'ga-presets': 'ga_parameter_presets',
    'constraint-submissions': 'study_programs',
};

/** Table behind an /api/internal path, e.g. "study-programs?x=1" => "study_programs". */
export function tableOf(url: string): string {
    const resource = url.replace(/^\//, '').split(/[/?]/)[0];

    return TABLE_OF_PATH[resource] ?? resource.replaceAll('-', '_');
}

/**
 * Calls callback when any client (web, desktop, this tab included) changes one of tables, and
 * after a reconnect. Bursts are merged into one call. Must be called during component setup.
 */
export function onResourcesChanged(tables: string[], callback: () => void, delay = 300): void {
    let timer: ReturnType<typeof setTimeout> | undefined;
    let stop: (() => void) | undefined;

    const listener = (resources: ChangedResources | null) => {
        if (resources !== null && !tables.some((table) => table in resources)) {
            return;
        }

        clearTimeout(timer);
        timer = setTimeout(callback, delay);
    };

    onMounted(() => {
        stop = listenForChanges(listener);
    });

    onUnmounted(() => {
        clearTimeout(timer);
        stop?.();
    });
}