<script setup lang="ts">
import Alert from '@/Components/ui/Alert.vue';
import Panel from '@/Components/ui/Panel.vue';
import { onResourcesChanged } from '@/composables/useResourceChanges';
import { api, toApiError, type ApiError } from '@/lib/api';
import type { RoomGridData, ScheduleScope } from '@/types/models';
import { computed, ref, watch } from 'vue';

const props = defineProps<{ period?: string; scope?: ScheduleScope }>();

const data = ref<RoomGridData | null>(null);
const loading = ref(false);
const failure = ref<ApiError | null>(null);
const buildingName = ref('');
const day = ref(0);
const clashOnly = ref(false);
let requestId = 0;

const building = computed(() => data.value?.buildings.find((b) => b.name === buildingName.value) ?? data.value?.buildings[0] ?? null);
const days = computed(() => {
    const seen = new Map<number, string>();
    for (const slot of data.value?.time_slots ?? []) seen.set(slot.day, slot.day_label);

    return [...seen].map(([value, label]) => ({ value, label }));
});
const slots = computed(() => (data.value?.time_slots ?? []).filter((s) => !day.value || s.day === day.value));
const cellOf = computed(() => {
    const map = new Map<string, NonNullable<typeof data.value>['cells'][number]>();
    for (const cell of data.value?.cells ?? []) map.set(`${cell.time_slot_id}:${cell.room_id}`, cell);

    return map;
});

const usedByBuilding = computed(() => {
    const roomBuilding = new Map<number, string>();
    for (const b of data.value?.buildings ?? []) for (const r of b.rooms) roomBuilding.set(r.id, b.name);
    const counts = new Map<string, number>();
    for (const cell of data.value?.cells ?? []) {
        const name = roomBuilding.get(cell.room_id);
        if (name) counts.set(name, (counts.get(name) ?? 0) + 1);
    }

    return counts;
});
const clashesInBuilding = computed(() => {
    const ids = new Set(building.value?.rooms.map((r) => r.id));

    return (data.value?.cells ?? []).filter((c) => c.clash && ids.has(c.room_id)).length;
});

function startsDay(index: number): boolean {
    return index === 0 || slots.value[index].day !== slots.value[index - 1].day;
}

function visible(slotId: number, roomId: number) {
    const cell = cellOf.value.get(`${slotId}:${roomId}`);

    return cell && (!clashOnly.value || cell.clash) ? cell : undefined;
}

function tooltip(cell: NonNullable<ReturnType<typeof visible>>): string {
    return cell.classes.map((c) => `${c.course} · ${c.class_label}\n${c.lecturer}${c.group ? ` (${c.group})` : ''}`).join('\n\n') + (cell.clash ? '\n\nBentrok: ruang atau dosen dipakai lebih dari satu kelas.' : '');
}

async function load(): Promise<void> {
    const id = ++requestId;
    loading.value = true;
    try {
        const response = await api.get<{ data: RoomGridData | null }>('schedules/room-grid', { params: { period: props.period, scope: props.scope } });
        if (id !== requestId) return;
        data.value = response.data.data;
        failure.value = null;
    } catch (e) {
        if (id === requestId) failure.value = toApiError(e);
    } finally {
        if (id === requestId) loading.value = false;
    }
}

watch(() => [props.period, props.scope], () => void load(), { immediate: true });
onResourcesChanged(['scheduling_runs', 'schedule_details'], () => void load());
</script>

<template>
    <div class="space-y-4">
        <Alert v-if="failure" :title="failure.message" />
        <p v-if="loading && !data" class="rounded-lg border border-gray-200 bg-white p-8 text-center text-sm text-gray-500">Memuat jadwal…</p>
        <p v-else-if="!data && !failure" class="rounded-lg border border-gray-200 bg-white p-8 text-center text-sm text-gray-500">Belum ada jadwal terpublikasi untuk periode ini. Tampilan ini mengikuti jadwal yang sudah dipublikasikan.</p>

        <template v-if="data && building">
            <div class="flex flex-wrap items-center justify-between gap-3">
                <div>
                    <h2 class="text-base font-bold text-gray-900">{{ data.title }}</h2>
                    <p class="text-xs text-gray-500">{{ data.subtitle }}</p>
                </div>
                <span
                    class="rounded-full px-3 py-1 text-xs font-bold"
                    :class="data.clash_count ? 'bg-red-600 text-white' : 'bg-green-100 text-green-800'"
                >{{ data.clash_count ? `${data.clash_count} tabrakan` : 'Tanpa tabrakan' }}</span>
            </div>

            <div class="flex flex-wrap items-center gap-x-6 gap-y-2" role="group" aria-label="Filter tampilan">
                <div class="flex flex-wrap gap-1.5" role="group" aria-label="Gedung">
                    <button
                        v-for="b in data.buildings"
                        :key="b.name"
                        type="button"
                        class="rounded-full px-3 py-1 text-xs font-semibold transition-colors focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-primary"
                        :class="b.name === building.name ? 'bg-primary text-white' : 'bg-gray-100 text-gray-600 hover:bg-gray-200'"
                        :aria-pressed="b.name === building.name"
                        @click="buildingName = b.name"
                    >{{ b.label }} <span class="font-normal opacity-80">· {{ usedByBuilding.get(b.name) ?? 0 }}</span></button>
                </div>
                <div class="flex flex-wrap gap-1.5" role="group" aria-label="Hari">
                    <button
                        v-for="d in [{ value: 0, label: 'Semua hari' }, ...days]"
                        :key="d.value"
                        type="button"
                        class="rounded-full px-3 py-1 text-xs font-semibold transition-colors focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-primary"
                        :class="day === d.value ? 'bg-gray-800 text-white' : 'bg-gray-100 text-gray-600 hover:bg-gray-200'"
                        :aria-pressed="day === d.value"
                        @click="day = d.value"
                    >{{ d.label }}</button>
                </div>
                <label v-if="data.clash_count" class="flex items-center gap-2 text-xs font-semibold text-gray-700">
                    <input v-model="clashOnly" type="checkbox" class="rounded border-gray-300 text-primary focus:ring-primary" />
                    Hanya yang bentrok<span v-if="clashesInBuilding" class="font-normal text-gray-500">({{ clashesInBuilding }} di gedung ini)</span>
                </label>
            </div>

            <ul class="flex flex-wrap gap-x-4 gap-y-1.5 text-xs text-gray-700" aria-label="Legenda warna">
                <li v-for="entry in data.legend" :key="entry.label" class="inline-flex items-center gap-1.5">
                    <i class="inline-block h-3 w-4 rounded-sm border border-[#b0b8c8]" :style="{ backgroundColor: entry.color, borderLeft: entry.left_border ? `3px solid ${entry.left_border}` : undefined }" aria-hidden="true" />
                    {{ entry.label }}
                </li>
                <li v-if="data.clash_count" class="inline-flex items-center gap-1.5">
                    <i class="inline-block h-3 w-4 rounded-sm bg-white ring-2 ring-inset ring-[#dc2626]" aria-hidden="true" />
                    Tabrakan
                </li>
            </ul>

            <Panel>
                <div class="max-h-[70vh] overflow-auto">
                    <table class="border-separate border-spacing-0 text-left text-xs">
                        <thead>
                            <tr>
                                <th class="sticky left-0 top-0 z-30 w-[88px] min-w-[88px] border-b border-r border-[#b0b8c8] bg-primary px-2 py-2 text-center font-semibold text-white">Sesi</th>
                                <th v-for="room in building.rooms" :key="room.id" class="sticky top-0 z-20 w-[132px] min-w-[132px] border-b border-r border-[#b0b8c8] bg-primary px-2 py-2 text-center font-semibold text-white">{{ room.code }}</th>
                            </tr>
                        </thead>
                        <tbody>
                            <template v-for="(slot, i) in slots" :key="slot.id">
                                <tr v-if="startsDay(i)">
                                    <th :colspan="building.rooms.length + 1" class="sticky left-0 border-b border-[#b0b8c8] bg-slate-200 px-2 py-1 text-left text-xs font-bold text-gray-900">{{ slot.day_label }}</th>
                                </tr>
                                <tr>
                                    <th class="sticky left-0 z-10 border-b border-r border-[#b0b8c8] bg-slate-100 px-2 py-1.5 text-left font-semibold text-primary">
                                        Sesi {{ slot.session }}<span class="block text-[10px] font-normal text-gray-500">{{ slot.time }}</span>
                                    </th>
                                    <td v-for="room in building.rooms" :key="room.id" class="h-[60px] w-[132px] min-w-[132px] max-w-[132px] border-b border-r border-[#b0b8c8] p-0 align-top">
                                        <div
                                            v-if="visible(slot.id, room.id)"
                                            class="h-full overflow-hidden px-1.5 py-1 text-[#1a1a1a]"
                                            :class="visible(slot.id, room.id)!.clash ? 'ring-2 ring-inset ring-[#dc2626]' : ''"
                                            :style="{ backgroundColor: visible(slot.id, room.id)!.color, borderLeft: visible(slot.id, room.id)!.left_border ? `3px solid ${visible(slot.id, room.id)!.left_border}` : undefined }"
                                            :title="tooltip(visible(slot.id, room.id)!)"
                                        >
                                            <template v-for="(c, n) in visible(slot.id, room.id)!.classes.slice(0, 2)" :key="n">
                                                <p class="line-clamp-2 text-[10px] font-bold leading-tight">{{ c.course }} · {{ c.class_label }}</p>
                                                <p class="truncate text-[9px] leading-tight">{{ c.lecturer }}</p>
                                                <p v-if="c.group" class="text-[9px] leading-tight text-[#555]">{{ c.group }}</p>
                                            </template>
                                            <p v-if="visible(slot.id, room.id)!.classes.length > 2" class="text-[9px] font-bold">+{{ visible(slot.id, room.id)!.classes.length - 2 }} kelas lain</p>
                                        </div>
                                    </td>
                                </tr>
                            </template>
                        </tbody>
                    </table>
                </div>
            </Panel>
            <p v-if="data.without_room" class="text-xs text-amber-700">{{ data.without_room }} kelas belum punya ruang dan tidak tampil di grid ini.</p>
        </template>
    </div>
</template>
