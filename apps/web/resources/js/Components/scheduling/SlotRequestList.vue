<script setup lang="ts">
import Button from '@/Components/ui/Button.vue';
import { formatDateTime, SLOT_REQUEST_STATUS_LABELS } from '@/lib/labels';
import type { SlotRequest, SlotRequestStatus } from '@/types/models';

/** History of requests for more room slots, newest first. */
defineProps<{ requests: SlotRequest[]; cancellable?: boolean; cancelling?: number | null }>();
const emit = defineEmits<{ cancel: [request: SlotRequest] }>();

const TONES: Record<SlotRequestStatus, string> = {
    pending: 'bg-blue-50 text-blue-700',
    fulfilled: 'bg-emerald-50 text-emerald-700',
    partially_fulfilled: 'bg-amber-50 text-amber-800',
    rejected: 'bg-red-50 text-red-700',
};
</script>

<template>
    <p v-if="!requests.length" class="rounded-lg border border-gray-200 bg-white px-4 py-6 text-center text-sm text-gray-500">Belum ada permintaan tambahan slot.</p>
    <ul v-else class="divide-y divide-gray-100 rounded-lg border border-gray-200 bg-white">
        <li v-for="request in requests" :key="request.id" class="space-y-2 px-4 py-3 text-sm">
            <div class="flex flex-wrap items-center justify-between gap-2">
                <span class="text-gray-600">Diajukan {{ formatDateTime(request.created_at) }}</span>
                <span class="rounded px-2 py-0.5 text-xs font-semibold" :class="TONES[request.status]">{{ SLOT_REQUEST_STATUS_LABELS[request.status] }}</span>
            </div>
            <ul class="space-y-0.5 text-xs text-gray-700">
                <li v-for="need in request.needs" :key="need.course_id">
                    {{ need.code ?? `MK #${need.course_id}` }} {{ need.name ?? '' }}: {{ need.meetings }} pertemuan, ruang minimal {{ need.min_capacity }} kursi
                </li>
            </ul>
            <p v-if="request.note" class="whitespace-pre-line text-xs text-gray-600">Catatan: {{ request.note }}</p>
            <p v-if="request.status !== 'pending'" class="text-xs text-gray-600">
                {{ request.allocations_count }} slot diberikan{{ request.reviewed_at ? `, ditanggapi ${formatDateTime(request.reviewed_at)}` : '' }}.
                <span v-if="request.response_note" class="block whitespace-pre-line">Tanggapan fakultas: {{ request.response_note }}</span>
            </p>
            <div v-if="cancellable && request.status === 'pending'" class="flex justify-end">
                <Button variant="secondary" size="sm" :loading="cancelling === request.id" @click="emit('cancel', request)">Batalkan permintaan</Button>
            </div>
        </li>
    </ul>
</template>
