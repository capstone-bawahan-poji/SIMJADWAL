<script setup lang="ts">
import Alert from '@/Components/ui/Alert.vue';
import Button from '@/Components/ui/Button.vue';
import Modal from '@/Components/Modal.vue';
import { useCollection } from '@/composables/useApiList';
import { api, type ApiError } from '@/lib/api';
import type { FacultySummary } from '@/types/models';
import { usePage } from '@inertiajs/vue3';
import { Download } from 'lucide-vue-next';
import { computed, ref } from 'vue';

const props = defineProps<{ period?: string }>();

type Format = 'xlsx' | 'pdf';
const FORMATS: { id: Format; label: string; hint: string }[] = [
    { id: 'xlsx', label: 'Excel (.xlsx)', hint: 'Satu sheet: slot waktu, gedung, dan ruang' },
    { id: 'pdf', label: 'PDF', hint: 'Satu halaman per gedung, kertas A3' },
];

const role = computed(() => usePage().props.auth.user.role);
const isSuperadmin = computed(() => role.value === 'superadmin');
const { items: faculties } = useCollection<FacultySummary>('faculties');

const open = ref(false);
const target = ref('all');
const format = ref<Format>('xlsx');
const downloading = ref(false);
const failure = ref<string | null>(null);

const targets = computed(() => [
    { value: 'all', label: 'Jadwal TPB dan semua fakultas' },
    { value: 'tpb', label: 'Jadwal TPB saja' },
    { value: 'faculty', label: 'Semua fakultas (tanpa TPB)' },
    ...faculties.value.map((f) => ({ value: `faculty:${f.id}`, label: `Fakultas ${f.code}: ${f.name}` })),
]);

function query(): Record<string, string> {
    const params: Record<string, string> = { format: format.value };
    if (props.period) params.period = props.period;
    if (!isSuperadmin.value || target.value === 'all') return params;
    if (target.value === 'tpb' || target.value === 'faculty') params.scope = target.value;
    else params.faculty_id = target.value.split(':')[1];

    return params;
}

async function failureOf(error: unknown): Promise<string> {
    const response = (error as { response?: { data?: Blob; status?: number } }).response;
    if (response?.data instanceof Blob) {
        try {
            const body = JSON.parse(await response.data.text()) as Partial<ApiError>;
            if (body.message) return body.message;
        } catch { /* not JSON */ }
    }

    return 'Gagal mengunduh jadwal. Coba lagi.';
}

async function download(): Promise<void> {
    downloading.value = true;
    failure.value = null;
    try {
        const response = await api.get<Blob>('schedules/export-room-grid', { params: query(), responseType: 'blob' });
        const name = /filename="?([^";]+)"?/.exec(String(response.headers['content-disposition'] ?? ''))?.[1] ?? `grid-ruangan.${format.value}`;
        const url = URL.createObjectURL(response.data);
        const link = document.createElement('a');
        link.href = url;
        link.download = name;
        link.click();
        URL.revokeObjectURL(url);
        open.value = false;
    } catch (e) {
        failure.value = await failureOf(e);
    } finally {
        downloading.value = false;
    }
}
</script>

<template>
    <Button variant="secondary" @click="failure = null; open = true">
        <Download class="size-4" aria-hidden="true" />
        Unduh seluruh jadwal
    </Button>

    <Modal :show="open" max-width="lg" close-button centered @close="open = false">
        <div class="rounded-t-lg border-b border-gray-100 bg-gray-50 py-5 pl-6 pr-14">
            <h2 class="text-lg font-bold text-gray-900">Unduh seluruh jadwal</h2>
            <p class="mt-0.5 text-sm text-gray-500">Jadwal terpublikasi: slot waktu ke bawah, gedung dan ruang ke samping. Sel yang bentrok diberi bingkai merah.</p>
        </div>
        <form class="space-y-5 p-6" @submit.prevent="download">
            <Alert v-if="failure" :title="failure" />

            <label v-if="isSuperadmin" class="block space-y-1 text-sm font-semibold text-gray-700">Yang diekspor
                <select v-model="target" class="h-10 w-full rounded-lg border-gray-200 bg-white text-sm text-gray-900 focus:border-primary focus:ring-primary">
                    <option v-for="t in targets" :key="t.value" :value="t.value">{{ t.label }}</option>
                </select>
            </label>
            <p v-else class="rounded-lg bg-gray-50 px-4 py-3 text-sm text-gray-600">
                Yang diekspor: jadwal {{ role === 'admin_tpb' ? 'TPB' : 'fakultas Anda' }}.
            </p>

            <fieldset>
                <legend class="text-sm font-semibold text-gray-700">Format</legend>
                <div class="mt-2 grid gap-2 sm:grid-cols-2">
                    <label
                        v-for="f in FORMATS"
                        :key="f.id"
                        class="flex cursor-pointer items-start gap-3 rounded-lg border p-3 text-sm"
                        :class="format === f.id ? 'border-primary bg-primary/5' : 'border-gray-200 hover:bg-gray-50'"
                    >
                        <input v-model="format" type="radio" name="all-schedules-format" :value="f.id" class="mt-0.5 border-gray-300 text-primary focus:ring-primary" />
                        <span>
                            <span class="block font-semibold text-gray-900">{{ f.label }}</span>
                            <span class="block text-xs text-gray-500">{{ f.hint }}</span>
                        </span>
                    </label>
                </div>
            </fieldset>

            <div class="flex justify-end gap-3 border-t border-gray-100 pt-4">
                <Button type="button" variant="secondary" @click="open = false">Batal</Button>
                <Button type="submit" :loading="downloading">Unduh</Button>
            </div>
        </form>
    </Modal>
</template>
