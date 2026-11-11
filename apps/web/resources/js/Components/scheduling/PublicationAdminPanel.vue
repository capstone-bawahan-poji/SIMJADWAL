<script setup lang="ts">
import Modal from '@/Components/Modal.vue';
import Alert from '@/Components/ui/Alert.vue';
import Button from '@/Components/ui/Button.vue';
import { onResourcesChanged } from '@/composables/useResourceChanges';
import { api, toApiError } from '@/lib/api';
import { formatDateTime } from '@/lib/labels';
import type { SchedulePublicationState } from '@/types/models';
import { ref, watch } from 'vue';

/**
 * Superadmin only: the publication of one faculty, and withdrawing it after a wrong entry. A
 * publication is otherwise final; withdrawing returns every program of the faculty to accepted.
 */
const props = defineProps<{ facultyId: number; period: string }>();
const emit = defineEmits<{ withdrawn: [] }>();

const publication = ref<SchedulePublicationState | null>(null);
const open = ref(false);
const note = ref('');
const processing = ref(false);
const failure = ref<string | null>(null);

async function load(): Promise<void> {
    if (!props.facultyId || !props.period) return;
    try {
        const response = await api.get<{ data: SchedulePublicationState }>(`faculties/${props.facultyId}/schedule-publication`, { params: { period: props.period } });
        publication.value = response.data.data;
    } catch {
        publication.value = null;
    }
}

async function withdraw(): Promise<void> {
    processing.value = true;
    failure.value = null;
    try {
        const response = await api.delete<{ data: SchedulePublicationState }>(`faculties/${props.facultyId}/schedule-publication`, {
            params: { period: props.period },
            data: { note: note.value.trim() },
        });
        publication.value = response.data.data;
        open.value = false;
        note.value = '';
        emit('withdrawn');
    } catch (error) {
        failure.value = toApiError(error).message;
    } finally {
        processing.value = false;
    }
}

watch(() => [props.facultyId, props.period], () => void load(), { immediate: true });
onResourcesChanged(['schedule_publications'], () => void load());
</script>

<template>
    <section v-if="publication" class="flex flex-wrap items-center justify-between gap-4 rounded-xl border border-gray-200 bg-white px-5 py-4 text-sm">
        <p v-if="publication.published" class="text-gray-700">
            Jadwal Fakultas {{ publication.faculty.code }} dipublikasikan {{ formatDateTime(publication.published_at) }} oleh {{ publication.published_by?.name ?? '-' }}.
        </p>
        <p v-else class="text-gray-600">Jadwal Fakultas {{ publication.faculty.code }} belum dipublikasikan pada periode ini.</p>
        <Button v-if="publication.published" variant="secondary" size="sm" @click="open = true; failure = null">Batalkan publikasi</Button>
    </section>

    <Modal :show="open" max-width="md" @close="open = false">
        <div class="space-y-4 p-6">
            <h2 class="text-lg font-bold text-gray-900">Batalkan publikasi jadwal Fakultas {{ publication?.faculty.code }}?</h2>
            <p class="text-sm text-gray-600">
                Dosen, mahasiswa, dan sistem kampus lain tidak lagi melihat jadwal ini. Semua program studi kembali berstatus diterima,
                sehingga fakultas dapat mengembalikan jadwal untuk diperbaiki lalu mempublikasikannya lagi. Alasan dicatat di log aktivitas.
            </p>
            <Alert v-if="failure" :title="failure" />
            <label class="block space-y-1 text-sm font-medium text-gray-700">
                Alasan
                <textarea v-model="note" rows="3" maxlength="1000" class="block w-full rounded-lg border-gray-200 text-sm focus:border-primary focus:ring-primary" />
            </label>
            <div class="flex justify-end gap-3">
                <Button variant="secondary" @click="open = false">Batal</Button>
                <Button variant="danger" :disabled="!note.trim()" :loading="processing" @click="withdraw">Batalkan publikasi</Button>
            </div>
        </div>
    </Modal>
</template>
