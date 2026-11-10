<script setup lang="ts">
import { computed, ref } from 'vue';
import type { ConstraintSubmissionDetail, ConstraintType, TimeSlot } from '@/types/models';

/**
 * Read-only constraint summary of one study program: hard rules, soft weights, courses still
 * missing lecturers and every lecturer's preferences by day. Shared by the prodi review page
 * and the faculty review dialog.
 */
const props = defineProps<{ detail: ConstraintSubmissionDetail; constraintTypes: ConstraintType[]; timeSlots: TimeSlot[] }>();

const hardConstraints = computed(() => props.constraintTypes.filter((c) => c.category === 'HC'));
const softConstraints = computed(() => props.constraintTypes.filter((c) => c.category === 'SC'));

const days = computed(() => {
    const byDay = new Map<number, string>();

    for (const slot of props.timeSlots) {
        byDay.set(slot.day, slot.day_label);
    }

    return [...byDay.entries()].sort(([a], [b]) => a - b).map(([day, label]) => ({ day, label }));
});

const slotById = computed(() => new Map(props.timeSlots.map((slot) => [slot.id, slot])));
const filledCount = computed(() => props.detail.lecturers.filter((lecturer) => lecturer.preferences.length > 0).length);

const search = ref('');
const hideEmpty = ref(false);

const lecturers = computed(() => {
    const query = search.value.trim().toLowerCase();

    return props.detail.lecturers
        .filter((lecturer) => !query || lecturer.name.toLowerCase().includes(query) || lecturer.nip.includes(query))
        .map((lecturer) => ({
            ...lecturer,
            want: lecturer.preferences.filter((preference) => preference.type === 'want').length,
            avoid: lecturer.preferences.filter((preference) => preference.type === 'avoid').length,
            perDay: days.value.map(({ day, label }) => {
                const preferences = lecturer.preferences.filter((preference) => slotById.value.get(preference.time_slot_id)?.day === day);

                return {
                    label,
                    want: preferences.filter((preference) => preference.type === 'want').length,
                    avoid: preferences.filter((preference) => preference.type === 'avoid').length,
                };
            }),
        }))
        .filter((lecturer) => !hideEmpty.value || lecturer.want + lecturer.avoid > 0);
});
</script>

<template>
    <div class="space-y-6">
        <div class="grid grid-cols-1 gap-4 md:grid-cols-2">
            <div class="rounded-lg border bg-white p-5" :class="detail.incomplete_courses.length ? 'border-amber-300' : 'border-gray-200'">
                <p class="text-sm text-gray-500">Dosen pengampu</p>
                <p v-if="detail.incomplete_courses.length === 0" class="mt-1 text-lg font-semibold text-green-700">Semua kelas sudah berdosen</p>
                <template v-else>
                    <p class="mt-1 text-lg font-semibold text-amber-800">{{ detail.incomplete_courses.length }} mata kuliah belum lengkap</p>
                    <ul class="mt-3 space-y-1 text-sm text-amber-800">
                        <li v-for="course in detail.incomplete_courses" :key="course.id">
                            <strong>{{ course.code }}</strong> {{ course.name }}: {{ course.assigned_class_count }} dari {{ course.parallel_class_count }} kelas berdosen
                        </li>
                    </ul>
                </template>
            </div>

            <div class="rounded-lg border border-gray-200 bg-white p-5">
                <p class="text-sm text-gray-500">Preferensi waktu</p>
                <p class="mt-1 text-lg font-semibold text-gray-900">
                    <span class="tabular-nums">{{ filledCount }}</span> dari <span class="tabular-nums">{{ detail.lecturers.length }}</span> dosen punya preferensi waktu
                </p>
                <p class="mt-1 text-sm text-gray-500">Dosen tanpa preferensi dianggap netral di semua slot.</p>
            </div>
        </div>

        <section class="grid gap-x-10 gap-y-8 rounded-lg border border-gray-200 bg-white p-6 lg:grid-cols-2">
            <div>
                <h3 class="text-sm font-semibold text-gray-900">Hard constraint</h3>
                <p class="mt-0.5 text-sm text-gray-500">Aturan baku tanpa pengecualian.</p>
                <ul class="mt-3 divide-y divide-gray-100 text-sm">
                    <li v-for="constraint in hardConstraints" :key="constraint.id" class="flex gap-3 py-2.5">
                        <span class="w-10 shrink-0 font-semibold text-gray-500">{{ constraint.code }}</span>
                        <span class="text-gray-800">{{ constraint.description }}</span>
                    </li>
                </ul>
            </div>

            <div>
                <h3 class="text-sm font-semibold text-gray-900">Bobot soft constraint</h3>
                <p class="mt-0.5 text-sm text-gray-500">Berlaku untuk semua program studi dan diatur admin fakultas.</p>
                <ul class="mt-3 divide-y divide-gray-100 text-sm">
                    <li v-for="constraint in softConstraints" :key="constraint.id" class="flex items-start justify-between gap-4 py-2.5">
                        <span class="text-gray-800">{{ constraint.description }}</span>
                        <span class="shrink-0 rounded bg-gray-100 px-2 py-0.5 text-xs font-semibold tabular-nums text-gray-700">Bobot {{ constraint.weight }}</span>
                    </li>
                </ul>
            </div>
        </section>

        <section class="overflow-hidden rounded-lg border border-gray-200 bg-white">
            <div class="flex flex-wrap items-center justify-between gap-x-6 gap-y-3 border-b border-gray-200 p-5">
                <div class="min-w-0">
                    <h3 class="text-sm font-semibold text-gray-900">Preferensi waktu dosen</h3>
                    <p class="mt-0.5 text-sm text-gray-500">
                        Slot per hari:
                        <span class="whitespace-nowrap font-medium text-emerald-700">+ diinginkan</span>,
                        <span class="whitespace-nowrap font-medium text-red-600">− dihindari</span>.
                    </p>
                </div>
                <div class="flex w-full flex-wrap items-center gap-x-4 gap-y-3 sm:w-auto">
                    <label class="flex shrink-0 items-center gap-2 whitespace-nowrap text-sm text-gray-600">
                        <input v-model="hideEmpty" type="checkbox" class="rounded border-gray-300 text-primary focus:ring-primary" />
                        Sembunyikan yang netral
                    </label>
                    <input
                        v-model="search"
                        type="search"
                        placeholder="Cari nama atau NIP dosen..."
                        aria-label="Cari dosen"
                        class="w-full rounded-lg border-gray-200 text-sm placeholder-gray-400 focus:border-primary focus:ring-primary sm:w-64"
                    />
                </div>
            </div>

            <p v-if="lecturers.length === 0" class="p-6 text-center text-sm text-gray-500">Tidak ada dosen yang cocok.</p>

            <div v-else class="overflow-x-auto">
                <table class="w-full border-collapse text-left text-sm">
                    <thead>
                        <tr class="border-b border-gray-200 bg-gray-50 text-xs font-medium text-gray-500">
                            <th class="min-w-[200px] px-6 py-3">Dosen</th>
                            <th v-for="day in days" :key="day.day" class="whitespace-nowrap px-3 py-3 text-center">{{ day.label }}</th>
                            <th class="whitespace-nowrap px-6 py-3 text-right">Total</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100">
                        <tr v-for="lecturer in lecturers" :key="lecturer.id">
                            <td class="px-6 py-3">
                                <p class="font-semibold text-gray-900">{{ lecturer.name }}<span v-if="lecturer.title" class="font-normal text-gray-500">, {{ lecturer.title }}</span></p>
                                <p class="text-xs tabular-nums text-gray-500">{{ lecturer.nip }}</p>
                            </td>
                            <td v-for="cell in lecturer.perDay" :key="cell.label" class="whitespace-nowrap px-3 py-3 text-center tabular-nums">
                                <template v-if="cell.want || cell.avoid">
                                    <span v-if="cell.want" class="font-medium text-emerald-700">+{{ cell.want }}</span>
                                    <span v-if="cell.avoid" class="ml-1.5 font-medium text-red-600">−{{ cell.avoid }}</span>
                                </template>
                                <span v-else class="text-gray-300">–</span>
                            </td>
                            <td class="whitespace-nowrap px-6 py-3 text-right text-sm">
                                <template v-if="lecturer.want || lecturer.avoid">
                                    <span class="font-semibold tabular-nums text-emerald-700">{{ lecturer.want }}</span>
                                    <span class="text-gray-400"> / </span>
                                    <span class="font-semibold tabular-nums text-red-600">{{ lecturer.avoid }}</span>
                                </template>
                                <span v-else class="text-gray-400">Netral</span>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </section>
    </div>
</template>
