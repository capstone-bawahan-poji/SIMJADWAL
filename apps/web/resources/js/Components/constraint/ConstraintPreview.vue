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

const search = ref('');
const lecturers = computed(() => {
    const query = search.value.trim().toLowerCase();

    return props.detail.lecturers
        .filter((l) => !query || l.name.toLowerCase().includes(query) || l.nip.includes(query))
        .map((l) => ({
            ...l,
            want: l.preferences.filter((p) => p.type === 'want').length,
            avoid: l.preferences.filter((p) => p.type === 'avoid').length,
            perDay: days.value.map(({ day, label }) => {
                const slots = l.preferences.filter((p) => slotById.value.get(p.time_slot_id)?.day === day);

                return { label, want: slots.filter((p) => p.type === 'want').length, avoid: slots.filter((p) => p.type === 'avoid').length };
            }),
        }));
});
</script>

<template>
    <div class="space-y-6">
        <div class="grid grid-cols-1 gap-6 lg:grid-cols-2">
            <section class="rounded-2xl border border-gray-100 bg-white p-6 shadow-sm">
                <h3 class="text-sm font-bold text-gray-900">Hard Constraint</h3>
                <p class="mb-4 text-xs text-gray-500">Aturan baku tanpa pengecualian.</p>
                <ul class="space-y-2">
                    <li v-for="hc in hardConstraints" :key="hc.id" class="flex items-start gap-3 rounded-xl border border-gray-100 bg-gray-50 px-4 py-3">
                        <span class="rounded bg-primary/10 px-1.5 py-0.5 font-mono text-[10px] font-bold text-primary">{{ hc.code }}</span>
                        <span class="text-xs text-gray-700">{{ hc.description }}</span>
                    </li>
                </ul>
            </section>

            <section class="rounded-2xl border border-gray-100 bg-white p-6 shadow-sm">
                <h3 class="text-sm font-bold text-gray-900">Bobot Soft Constraint</h3>
                <p class="mb-4 text-xs text-gray-500">Bobot berlaku untuk semua program studi dan diatur admin fakultas.</p>
                <ul class="space-y-2">
                    <li v-for="sc in softConstraints" :key="sc.id" class="flex items-center justify-between gap-3 rounded-xl border border-gray-100 bg-gray-50 px-4 py-3">
                        <div class="min-w-0">
                            <span class="font-mono text-[10px] font-bold text-gray-500">{{ sc.code }}</span>
                            <p class="text-xs text-gray-700">{{ sc.description }}</p>
                        </div>
                        <span class="shrink-0 text-sm font-bold text-gray-900">{{ sc.weight }}</span>
                    </li>
                </ul>
            </section>
        </div>

        <section class="rounded-2xl border bg-white p-6 shadow-sm" :class="detail.incomplete_courses.length ? 'border-amber-200' : 'border-gray-100'">
            <h3 class="text-sm font-bold text-gray-900">Kelengkapan Dosen Pengampu</h3>
            <p v-if="detail.incomplete_courses.length === 0" class="mt-1 text-xs text-emerald-700">Semua kelas mata kuliah sudah memiliki dosen pengampu.</p>
            <ul v-else class="mt-3 space-y-1.5 text-xs text-amber-800">
                <li v-for="course in detail.incomplete_courses" :key="course.id">
                    <strong>{{ course.code }}</strong> {{ course.name }}: {{ course.assigned_class_count }} dari {{ course.parallel_class_count }} kelas berdosen
                </li>
            </ul>
        </section>

        <section class="rounded-2xl border border-gray-100 bg-white shadow-sm">
            <div class="flex flex-col gap-3 border-b border-gray-100 p-6 md:flex-row md:items-center md:justify-between">
                <div>
                    <h3 class="text-sm font-bold text-gray-900">Preferensi Waktu Dosen</h3>
                    <p class="text-xs text-gray-500">Jumlah slot diinginkan (+) dan dihindari (−) per hari.</p>
                </div>
                <input v-model="search" type="search" placeholder="Cari nama atau NIP dosen..." aria-label="Cari dosen" class="w-full rounded-xl border-gray-200 bg-gray-50 text-xs placeholder-gray-400 focus:border-primary focus:ring-primary md:w-64" />
            </div>
            <p v-if="lecturers.length === 0" class="p-6 text-center text-sm text-gray-500">Tidak ada dosen yang cocok.</p>
            <div v-for="lecturer in lecturers" :key="lecturer.id" class="border-b border-gray-100 p-6 last:border-b-0">
                <div class="flex flex-col gap-2 md:flex-row md:items-center md:justify-between">
                    <div>
                        <h4 class="text-sm font-bold text-gray-900">{{ lecturer.name }}<span v-if="lecturer.title" class="font-normal text-gray-500">, {{ lecturer.title }}</span></h4>
                        <span class="font-mono text-[11px] text-gray-400">{{ lecturer.nip }}</span>
                    </div>
                    <div class="flex shrink-0 gap-3 text-[11px] font-semibold">
                        <span class="text-emerald-600">{{ lecturer.want }} slot diinginkan</span>
                        <span class="text-red-600">{{ lecturer.avoid }} slot dihindari</span>
                    </div>
                </div>
                <div v-if="lecturer.want + lecturer.avoid > 0" class="mt-3 grid grid-cols-2 gap-2 sm:grid-cols-5">
                    <div v-for="day in lecturer.perDay" :key="day.label" class="rounded-xl border border-gray-100 bg-gray-50 px-3 py-2 text-center">
                        <div class="text-[10px] font-bold uppercase tracking-wider text-gray-400">{{ day.label }}</div>
                        <div class="mt-0.5 text-xs font-semibold"><span class="text-emerald-600">+{{ day.want }}</span> <span class="text-red-600">−{{ day.avoid }}</span></div>
                    </div>
                </div>
                <p v-else class="mt-2 text-xs text-gray-400">Belum ada preferensi: semua slot netral.</p>
            </div>
        </section>
    </div>
</template>
