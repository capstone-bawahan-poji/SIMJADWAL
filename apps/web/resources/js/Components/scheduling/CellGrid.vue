<script setup lang="ts">
import { formatTime } from '@/lib/labels';
import type { CellView, GridRoom, TimeSlot } from '@/types/models';

/**
 * Rooms × sessions of one day. Rows are rooms, so a building with many rooms scrolls down,
 * not sideways. Clicking an enabled cell emits select.
 */
defineProps<{
    rooms: GridRoom[];
    /** The time slots of the day shown, one per session. */
    slots: TimeSlot[];
    view: (slot: TimeSlot, room: GridRoom) => CellView;
    caption?: string;
}>();
const emit = defineEmits<{ select: [slot: TimeSlot, room: GridRoom] }>();
</script>

<template>
    <div class="overflow-x-auto rounded-xl border border-gray-200 bg-white">
        <table class="w-full min-w-[640px] border-collapse text-left text-sm">
            <caption v-if="caption" class="sr-only">{{ caption }}</caption>
            <thead>
                <tr>
                    <th scope="col" class="sticky left-0 z-20 min-w-36 border-b border-r border-gray-200 bg-gray-50 px-4 py-3 font-semibold text-gray-700">Ruang</th>
                    <th v-for="slot in slots" :key="slot.id" scope="col" class="min-w-32 border-b border-r border-gray-200 bg-gray-50 px-3 py-3 text-center font-semibold text-gray-700">
                        <span class="block">Sesi {{ slot.session }}</span>
                        <span class="mt-0.5 block whitespace-nowrap text-[11px] font-normal text-gray-500">{{ formatTime(slot.start_time) }}–{{ formatTime(slot.end_time) }}</span>
                    </th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-100">
                <tr v-if="rooms.length === 0">
                    <td :colspan="slots.length + 1" class="px-4 py-8 text-center text-sm text-gray-500">Tidak ada ruang untuk filter ini.</td>
                </tr>
                <tr v-for="room in rooms" :key="room.id">
                    <th scope="row" class="sticky left-0 z-10 border-r border-gray-200 bg-white px-4 py-2.5 font-semibold text-gray-900">
                        {{ room.code }}
                        <span class="block text-[11px] font-normal text-gray-500">{{ room.capacity }} kursi{{ room.faculty_id === null ? ', bersama' : '' }}</span>
                    </th>
                    <td v-for="slot in slots" :key="slot.id" class="border-r border-gray-100 p-1.5">
                        <button
                            type="button"
                            class="flex min-h-10 w-full items-center justify-center rounded-md px-2 text-xs font-semibold transition-colors focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-primary disabled:cursor-not-allowed"
                            :class="[view(slot, room).tone, view(slot, room).pending ? 'outline-dashed outline-2 outline-offset-1 outline-gray-700' : '']"
                            :title="view(slot, room).title"
                            :disabled="view(slot, room).disabled"
                            @click="emit('select', slot, room)"
                        >{{ view(slot, room).label }}</button>
                    </td>
                </tr>
            </tbody>
        </table>
    </div>
</template>
