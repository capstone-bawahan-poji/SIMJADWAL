<script setup lang="ts">
import AppLayout from '@/Layouts/AppLayout.vue';
import { onResourcesChanged } from '@/composables/useResourceChanges';
import type { ConstraintStatus, StudyProgramDashboard } from '@/types/models';
import { Head, Link, router } from '@inertiajs/vue3';
import { ArrowRight, BookOpen, CalendarDays, Check, SlidersHorizontal, Users } from 'lucide-vue-next';
import { computed, ref } from 'vue';

const props = defineProps<{ dashboard: StudyProgramDashboard }>();

const C = {
    paper: '#F9F7F7',
    mist: '#DBE2EF',
    blue: '#3F72AF',
    navy: '#112D4E',
    white: '#FFFFFF',
    muted: 'rgba(17, 45, 78, 0.65)',
    track: 'rgba(219, 226, 239, 0.6)',
    danger: '#dc2626',
};
const constraint = computed(() => props.dashboard.constraint);
const schedule = computed(() => props.dashboard.schedule);
const format = (value: number) => value.toLocaleString('id-ID');
const dateFormat = new Intl.DateTimeFormat('id-ID', { dateStyle: 'long' });

function formatDate(value: string): string {
    const date = new Date(value);

    return Number.isNaN(date.getTime()) ? value : dateFormat.format(date);
}

function percentage(part: number, total: number): number {
    return total > 0 ? Math.round((part / total) * 100) : 0;
}

const completeCourses = computed(() => constraint.value.courses_count - constraint.value.incomplete_courses_count);
const pendingLecturers = computed(() => constraint.value.lecturers_count - constraint.value.lecturers_with_preferences_count);
const isDraft = computed(() => constraint.value.status === 'draft');
const isPublished = computed(() => schedule.value.status === 'published');

const CONSTRAINT_STATUS_LABEL: Record<ConstraintStatus, string> = {
    draft: 'Draft',
    submitted: 'Menunggu review fakultas',
    accepted: 'Diterima fakultas',
};
const constraintStatusLabel = computed(() => CONSTRAINT_STATUS_LABEL[constraint.value.status]);
const scheduleStatusLabel = computed(() => {
    if (isPublished.value) {
        return schedule.value.published_at ? `Diterbitkan ${formatDate(schedule.value.published_at)}` : 'Diterbitkan';
    }

    return schedule.value.status === 'active' ? 'Belum diterbitkan fakultas' : 'Belum ada jadwal';
});

interface NextAction {
    tone: 'action' | 'wait' | 'done';
    title: string;
    hint: string;
    label?: string;
    routeName?: string;
}

const next = computed<NextAction>(() => {
    if (constraint.value.courses_count === 0) {
        return {
            tone: 'action',
            title: 'Belum ada mata kuliah',
            hint: 'Tambahkan manual atau import dari file XLSX.',
            label: 'Buka data mata kuliah',
            routeName: 'prodi.courses.index',
        };
    }

    if (constraint.value.incomplete_courses_count > 0) {
        return {
            tone: 'action',
            title: `${format(constraint.value.incomplete_courses_count)} mata kuliah belum lengkap dosennya`,
            hint: 'Constraint tidak bisa di-submit sebelum semua kelas punya dosen.',
            label: 'Atur mapping dosen',
            routeName: 'prodi.mappings.index',
        };
    }

    if (isDraft.value && pendingLecturers.value > 0) {
        return {
            tone: 'action',
            title: `${format(pendingLecturers.value)} dosen belum mengisi preferensi`,
            hint: 'Yang belum mengisi dianggap netral di semua slot.',
            label: 'Kelola preferensi dosen',
            routeName: 'prodi.constraints.index',
        };
    }

    if (isDraft.value) {
        return {
            tone: 'action',
            title: 'Constraint siap di-submit',
            hint: 'Setelah di-submit, data prodi terkunci sampai fakultas mengembalikannya.',
            label: 'Review & submit',
            routeName: 'prodi.constraints.review',
        };
    }

    if (!isPublished.value) {
        return {
            tone: 'wait',
            title: constraint.value.status === 'accepted' ? 'Menunggu fakultas menerbitkan jadwal' : 'Menunggu review fakultas',
            hint: 'Tidak ada yang perlu dikerjakan prodi saat ini.',
        };
    }

    return { tone: 'done', title: 'Semua tahap selesai', hint: 'Jadwal prodi sudah diterbitkan.' };
});

const dark = computed(() => next.value.tone !== 'wait');
const band = computed(() =>
    dark.value
        ? { bg: C.navy, fg: C.paper, sub: 'rgba(249, 247, 247, 0.72)', eyebrow: C.mist, arcTrack: 'rgba(249, 247, 247, 0.16)' }
        : { bg: C.mist, fg: C.navy, sub: C.muted, eyebrow: C.blue, arcTrack: 'rgba(255, 255, 255, 0.75)' },
);
const eyebrowText = computed(() => (next.value.tone === 'action' ? 'Langkah berikutnya' : next.value.tone === 'done' ? 'Selesai' : 'Status'));

const arcs = computed(() => {
    const colors = dark.value
        ? [{ color: C.paper, opacity: 1 }, { color: C.mist, opacity: 1 }, { color: C.blue, opacity: 1 }]
        : [{ color: C.navy, opacity: 1 }, { color: C.blue, opacity: 1 }, { color: C.navy, opacity: 0.45 }];

    return [
        { radius: 52, value: percentage(completeCourses.value, constraint.value.courses_count), label: 'Dosen pengampu' },
        {
            radius: 40,
            value: percentage(constraint.value.lecturers_with_preferences_count, constraint.value.lecturers_count),
            label: 'Preferensi dosen',
        },
        { radius: 28, value: constraint.value.completeness_percent, label: 'Kelengkapan total' },
    ].map((arc, index) => ({
        ...arc,
        ...colors[index],
        path: `M ${60 - arc.radius} 60 A ${arc.radius} ${arc.radius} 0 0 1 ${60 + arc.radius} 60`,
        length: Math.PI * arc.radius,
    }));
});

const card = {
    backgroundColor: C.white,
    border: `1px solid ${C.mist}`,
    borderRadius: '20px',
    padding: '24px',
};

const figures = computed(() => [
    { label: 'Mata kuliah', value: format(constraint.value.courses_count) },
    { label: 'Dosen', value: format(constraint.value.lecturers_count) },
    { label: 'Kelas terjadwal', value: isPublished.value ? format(schedule.value.classes_count ?? 0) : '–' },
]);

const bars = computed(() => [
    {
        label: 'Dosen pengampu',
        value: percentage(completeCourses.value, constraint.value.courses_count),
        detail: `${format(completeCourses.value)} dari ${format(constraint.value.courses_count)} mata kuliah sudah berdosen`,
    },
    {
        label: 'Preferensi dosen',
        value: percentage(constraint.value.lecturers_with_preferences_count, constraint.value.lecturers_count),
        detail: `${format(constraint.value.lecturers_with_preferences_count)} dari ${format(constraint.value.lecturers_count)} dosen sudah mengisi`,
    },
    {
        label: 'Submit',
        value: isDraft.value ? 0 : 100,
        detail: isDraft.value ? 'Belum di-submit ke fakultas' : constraintStatusLabel.value,
    },
    { label: 'Jadwal', value: isPublished.value ? 100 : 0, detail: scheduleStatusLabel.value },
]);
const hoveredBar = ref<number | null>(null);
const PLOT_HEIGHT = 176;

const tasks = computed(() => [
    {
        label: 'Mata kuliah belum berdosen',
        value: constraint.value.incomplete_courses_count === 0 ? 'Selesai' : format(constraint.value.incomplete_courses_count),
        done: constraint.value.incomplete_courses_count === 0,
        routeName: 'prodi.mappings.index',
    },
    {
        label: 'Dosen belum isi preferensi',
        value: pendingLecturers.value === 0 ? 'Selesai' : format(pendingLecturers.value),
        done: pendingLecturers.value === 0,
        routeName: 'prodi.constraints.index',
    },
    {
        label: 'Submit constraint ke fakultas',
        value: isDraft.value ? 'Draft' : 'Selesai',
        done: !isDraft.value,
        routeName: isDraft.value ? 'prodi.constraints.review' : 'prodi.constraints.index',
    },
]);

type StepState = 'done' | 'todo' | 'wait';
const STATE: Record<StepState, { text: string; label: string; weight: number }> = {
    done: { text: C.blue, label: 'Selesai', weight: 500 },
    todo: { text: C.navy, label: 'Perlu tindakan', weight: 700 },
    wait: { text: C.muted, label: 'Menunggu', weight: 500 },
};

const steps = computed(() => [
    {
        title: 'Mata kuliah',
        icon: BookOpen,
        state: (constraint.value.courses_count > 0 ? 'done' : 'todo') as StepState,
        figure: format(constraint.value.courses_count),
        caption: 'terdaftar di prodi',
        routeName: 'prodi.courses.index',
    },
    {
        title: 'Dosen pengampu',
        icon: Users,
        state: (constraint.value.courses_count > 0 && constraint.value.incomplete_courses_count === 0 ? 'done' : 'todo') as StepState,
        figure: `${format(completeCourses.value)}/${format(constraint.value.courses_count)}`,
        caption: 'mata kuliah berdosen',
        routeName: 'prodi.mappings.index',
    },
    {
        title: 'Constraint',
        icon: SlidersHorizontal,
        state: (isDraft.value ? 'todo' : 'done') as StepState,
        figure: `${constraint.value.completeness_percent}%`,
        caption: isDraft.value ? 'belum di-submit' : constraintStatusLabel.value,
        routeName: 'prodi.constraints.index',
    },
    {
        title: 'Jadwal',
        icon: CalendarDays,
        state: (isPublished.value ? 'done' : 'wait') as StepState,
        figure: isPublished.value ? `${format(schedule.value.classes_count ?? 0)} kelas` : '–',
        caption: isPublished.value
            ? `${format(schedule.value.sks_total ?? 0)} SKS · ${format(schedule.value.hc_violations ?? 0)} bentrok`
            : scheduleStatusLabel.value,
        routeName: 'prodi.schedule-matrix.index',
    },
]);

onResourcesChanged(['scheduling_runs', 'schedule_details', 'study_programs', 'lecturers', 'courses', 'course_lecturers', 'lecturer_preferences'], () => {
    router.reload({ only: ['dashboard'] });
});
</script>

<template>
    <Head title="Dashboard Koor Prodi" />

    <AppLayout>
        <div
            class="w-full p-6 md:p-8"
            :style="{ backgroundColor: C.paper, minHeight: 'calc(100vh - 4rem)', display: 'flex', flexDirection: 'column', gap: '24px' }"
        >
            <div>
                <h1 class="text-3xl font-bold tracking-tight" :style="{ color: C.navy }">Dashboard</h1>
                <p class="mt-1 text-sm" :style="{ color: C.muted }">Koor Prodi · {{ dashboard.period }}</p>
            </div>

            <section :style="{ ...card, padding: '20px 24px' }" aria-label="Ringkasan prodi">
                <dl style="display: grid; grid-template-columns: repeat(3, minmax(0, 1fr)); margin: 0">
                    <div
                        v-for="(figure, index) in figures"
                        :key="figure.label"
                        :style="{
                            paddingLeft: index === 0 ? '0' : '24px',
                            paddingRight: '12px',
                            borderLeft: index === 0 ? 'none' : `1px solid ${C.mist}`,
                        }"
                    >
                        <dt class="text-sm" :style="{ color: C.muted }">{{ figure.label }}</dt>
                        <dd class="mt-1 text-3xl font-bold tabular-nums tracking-tight" :style="{ color: C.navy }">{{ figure.value }}</dd>
                    </div>
                </dl>
            </section>

            <div style="display: flex; flex-wrap: wrap; gap: 24px; align-items: stretch">
                <section :style="{ ...card, flex: '1.6 1 32rem', minWidth: 0 }" aria-labelledby="progress-heading">
                        <div class="flex flex-wrap items-baseline justify-between gap-2">
                            <h2 id="progress-heading" class="text-lg font-bold" :style="{ color: C.navy }">Progres tahap</h2>
                            <span class="text-xs" :style="{ color: C.muted }">Persentase tiap tahap penyusunan jadwal</span>
                        </div>

                        <div :style="{ display: 'flex', gap: '12px', paddingTop: '32px' }">
                            <div
                                class="text-xs tabular-nums"
                                :style="{
                                    color: C.muted,
                                    display: 'flex',
                                    flexDirection: 'column',
                                    justifyContent: 'space-between',
                                    height: `${PLOT_HEIGHT}px`,
                                    textAlign: 'right',
                                    width: '2.25rem',
                                    flexShrink: 0,
                                }"
                            >
                                <span>100%</span><span>50%</span><span>0%</span>
                            </div>

                            <div style="flex: 1; min-width: 0">
                                <div
                                    :style="{
                                        position: 'relative',
                                        height: `${PLOT_HEIGHT}px`,
                                        display: 'grid',
                                        gridTemplateColumns: 'repeat(4, minmax(0, 1fr))',
                                        gap: '16px',
                                        borderBottom: `1px solid ${C.mist}`,
                                    }"
                                >
                                    <div :style="{ position: 'absolute', left: 0, right: 0, top: 0, borderTop: `1px dashed ${C.mist}` }"></div>
                                    <div :style="{ position: 'absolute', left: 0, right: 0, top: '50%', borderTop: `1px dashed ${C.mist}` }"></div>

                                    <div
                                        v-for="(bar, index) in bars"
                                        :key="bar.label"
                                        tabindex="0"
                                        :aria-label="`${bar.label}: ${bar.value}%. ${bar.detail}`"
                                        :style="{ position: 'relative', display: 'flex', justifyContent: 'center', alignItems: 'flex-end', outline: 'none' }"
                                        @mouseenter="hoveredBar = index"
                                        @mouseleave="hoveredBar = null"
                                        @focus="hoveredBar = index"
                                        @blur="hoveredBar = null"
                                    >
                                        <div
                                            :style="{
                                                position: 'relative',
                                                width: '56px',
                                                maxWidth: '100%',
                                                height: '100%',
                                                backgroundColor: C.track,
                                                borderRadius: '14px 14px 0 0',
                                                overflow: 'hidden',
                                                display: 'flex',
                                                alignItems: 'flex-end',
                                            }"
                                        >
                                            <div
                                                :style="{
                                                    width: '100%',
                                                    height: `${bar.value > 0 ? Math.max(bar.value, 3) : 0}%`,
                                                    backgroundColor: hoveredBar === index ? C.navy : C.blue,
                                                    borderRadius: '14px 14px 0 0',
                                                    transition: 'background-color .15s',
                                                }"
                                            ></div>
                                        </div>

                                        <div
                                            v-if="hoveredBar === index"
                                            role="status"
                                            :style="{
                                                position: 'absolute',
                                                bottom: `calc(${bar.value}% + 10px)`,
                                                left: '50%',
                                                transform: 'translateX(-50%)',
                                                zIndex: 5,
                                                backgroundColor: C.navy,
                                                borderRadius: '10px',
                                                padding: '8px 12px',
                                                boxShadow: '0 6px 20px rgba(17,45,78,.25)',
                                                width: 'max-content',
                                                maxWidth: '14rem',
                                                pointerEvents: 'none',
                                            }"
                                        >
                                            <p class="tabular-nums" :style="{ fontWeight: 700, color: C.paper, fontSize: '1rem' }">{{ bar.value }}%</p>
                                            <p class="text-xs" :style="{ color: C.mist }">{{ bar.detail }}</p>
                                        </div>
                                    </div>
                                </div>
                                <div style="display: grid; grid-template-columns: repeat(4, minmax(0, 1fr)); gap: 16px; margin-top: 10px">
                                    <span v-for="bar in bars" :key="bar.label" class="text-center text-xs" :style="{ color: C.muted }">{{ bar.label }}</span>
                                </div>
                            </div>
                        </div>
                </section>

                <div style="flex: 1 1 22rem; min-width: 0; display: flex; flex-direction: column; gap: 24px">
                    <section
                        :style="{
                            backgroundColor: band.bg,
                            borderRadius: '20px',
                            padding: '24px',
                            display: 'flex',
                            alignItems: 'center',
                            justifyContent: 'space-between',
                            gap: '16px',
                            flexWrap: 'wrap',
                        }"
                        aria-labelledby="next-heading"
                    >
                        <div style="flex: 1 1 11rem; min-width: 0">
                            <p id="next-heading" class="text-xs font-semibold uppercase tracking-wide" :style="{ color: band.eyebrow }">{{ eyebrowText }}</p>
                            <p class="mt-1.5 text-xl font-bold leading-snug" :style="{ color: band.fg }">{{ next.title }}</p>
                            <p class="mt-1 text-sm" :style="{ color: band.sub }">{{ next.hint }}</p>
                        </div>

                        <div :style="{ position: 'relative', width: '9.5rem', flexShrink: 0, paddingBottom: '16px' }">
                            <svg viewBox="0 0 120 66" role="img" aria-label="Kelengkapan: dosen pengampu, preferensi dosen, dan kelengkapan total" style="display: block; width: 100%">
                                <g v-for="arc in arcs" :key="arc.label">
                                    <title>{{ arc.label }} {{ arc.value }}%</title>
                                    <path :d="arc.path" fill="none" :stroke="band.arcTrack" stroke-width="8" stroke-linecap="round" />
                                    <path
                                        v-if="arc.value > 0"
                                        :d="arc.path"
                                        fill="none"
                                        :stroke="arc.color"
                                        :stroke-opacity="arc.opacity"
                                        stroke-width="8"
                                        stroke-linecap="round"
                                        :stroke-dasharray="`${(arc.length * arc.value) / 100} ${arc.length}`"
                                    />
                                </g>
                            </svg>
                            <Link
                                v-if="next.routeName"
                                :href="route(next.routeName)"
                                :aria-label="next.label"
                                :style="{
                                    position: 'absolute',
                                    left: '50%',
                                    bottom: '0',
                                    transform: 'translateX(-50%)',
                                    width: '44px',
                                    height: '44px',
                                    borderRadius: '9999px',
                                    backgroundColor: C.paper,
                                    color: C.navy,
                                    display: 'flex',
                                    alignItems: 'center',
                                    justifyContent: 'center',
                                    boxShadow: '0 6px 14px rgba(0,0,0,.25)',
                                }"
                            >
                                <ArrowRight class="h-5 w-5" aria-hidden="true" />
                            </Link>
                            <span
                                v-else
                                :style="{
                                    position: 'absolute',
                                    left: '50%',
                                    bottom: '0',
                                    transform: 'translateX(-50%)',
                                    width: '44px',
                                    height: '44px',
                                    borderRadius: '9999px',
                                    backgroundColor: next.tone === 'done' ? C.blue : C.white,
                                    color: next.tone === 'done' ? C.white : C.navy,
                                    display: 'flex',
                                    alignItems: 'center',
                                    justifyContent: 'center',
                                    fontSize: '12px',
                                    fontWeight: 700,
                                }"
                            >
                                <Check v-if="next.tone === 'done'" class="h-5 w-5" aria-hidden="true" />
                                <template v-else>{{ constraint.completeness_percent }}%</template>
                            </span>
                        </div>
                    </section>

                    <section :style="{ ...card, flex: 1 }" aria-labelledby="tasks-heading">
                        <h2 id="tasks-heading" class="text-lg font-bold" :style="{ color: C.navy }">Perlu dikerjakan</h2>
                        <ul style="list-style: none; padding: 0; margin: 12px 0 0">
                            <li v-for="(task, index) in tasks" :key="task.label" :style="{ borderTop: index === 0 ? 'none' : `1px solid ${C.mist}` }">
                                <Link :href="route(task.routeName)" style="display: flex; align-items: center; gap: 12px; padding: 12px 0">
                                    <span
                                        :style="{
                                            width: '34px',
                                            height: '34px',
                                            borderRadius: '9999px',
                                            flexShrink: 0,
                                            display: 'flex',
                                            alignItems: 'center',
                                            justifyContent: 'center',
                                            fontSize: '13px',
                                            fontWeight: 700,
                                            backgroundColor: task.done ? C.mist : C.navy,
                                            color: task.done ? C.blue : C.paper,
                                        }"
                                    >
                                        <Check v-if="task.done" class="h-4 w-4" aria-hidden="true" />
                                        <template v-else>{{ index + 1 }}</template>
                                    </span>
                                    <span class="text-sm" :style="{ color: C.navy, flex: 1, minWidth: 0 }">{{ task.label }}</span>
                                    <span class="text-sm font-bold tabular-nums" :style="{ color: task.done ? C.blue : C.navy }">{{ task.value }}</span>
                                </Link>
                            </li>
                        </ul>
                    </section>
                </div>
            </div>

            <section
                aria-labelledby="flow-heading"
                :style="{
                    backgroundColor: C.mist,
                    borderRadius: '24px',
                    padding: '24px',
                    display: 'flex',
                    flexWrap: 'wrap',
                    alignItems: 'center',
                    gap: '24px',
                }"
            >
                <div style="flex: 1 1 13rem; min-width: 0">
                    <h2 id="flow-heading" class="text-xl font-bold" :style="{ color: C.navy }">Alur kerja</h2>
                    <p class="mt-2 text-sm" :style="{ color: C.muted }">Empat tahap penyusunan jadwal prodi untuk {{ dashboard.period }}.</p>
                </div>

                <ol style="flex: 4 1 32rem; min-width: 0; display: flex; flex-wrap: wrap; gap: 16px; list-style: none; padding: 0; margin: 0">
                    <li v-for="step in steps" :key="step.title" style="flex: 1 1 9rem; min-width: 0">
                        <Link
                            :href="route(step.routeName)"
                            :style="{
                                display: 'flex',
                                flexDirection: 'column',
                                height: '100%',
                                backgroundColor: C.white,
                                borderRadius: '18px',
                                padding: '16px',
                                boxShadow: '0 8px 20px rgba(17,45,78,.08)',
                            }"
                        >
                            <span :style="{ width: '34px', height: '34px', borderRadius: '9999px', backgroundColor: C.blue, color: C.white, display: 'flex', alignItems: 'center', justifyContent: 'center' }">
                                <component :is="step.icon" class="h-4 w-4" aria-hidden="true" />
                            </span>
                            <p class="mt-3 text-sm font-bold" :style="{ color: C.navy }">{{ step.title }}</p>
                            <p
                                class="text-xs"
                                :style="{
                                    color: step.title === 'Jadwal' && isPublished && (schedule.hc_violations ?? 0) > 0 ? C.danger : C.muted,
                                }"
                            >
                                {{ step.caption }}
                            </p>
                            <p class="mt-3 text-xl font-bold tabular-nums" :style="{ color: C.navy }">{{ step.figure }}</p>
                            <p class="mt-1 text-xs" :style="{ color: STATE[step.state].text, fontWeight: STATE[step.state].weight }">{{ STATE[step.state].label }}</p>
                        </Link>
                    </li>

                    <li style="flex: 0 1 8rem; min-width: 0">
                        <Link
                            :href="route('prodi.schedule-matrix.index')"
                            :style="{
                                display: 'flex',
                                flexDirection: 'column',
                                justifyContent: 'space-between',
                                height: '100%',
                                minHeight: '9rem',
                                backgroundColor: C.navy,
                                color: C.paper,
                                borderRadius: '18px',
                                padding: '16px',
                            }"
                        >
                            <span class="text-lg font-bold leading-tight">Matriks Jadwal</span>
                            <span :style="{ width: '30px', height: '30px', borderRadius: '9999px', backgroundColor: C.blue, color: C.white, display: 'flex', alignItems: 'center', justifyContent: 'center' }">
                                <ArrowRight class="h-4 w-4" aria-hidden="true" />
                            </span>
                        </Link>
                    </li>
                </ol>
            </section>
        </div>
    </AppLayout>
</template>
