<script setup lang="ts">
import { api } from '@/lib/api';
import { ROLE_LABELS } from '@/lib/labels';
import type { User } from '@/types';
import type { Collection, Department, Faculty, Paginated, Room, StudyProgram } from '@/types/models';
import { Link, router } from '@inertiajs/vue3';
import { computed, onBeforeUnmount, onMounted, ref, watch } from 'vue';

interface Result {
    key: string;
    title: string;
    subtitle: string;
    href: string;
}

interface Group {
    label: string;
    results: Result[];
}

const MAX_PER_GROUP = 5;

const query = ref('');
const open = ref(false);
const loading = ref(false);
const groups = ref<Group[]>([]);
const root = ref<HTMLElement | null>(null);

let lookups: Promise<[Faculty[], Department[], StudyProgram[]]> | null = null;

function loadLookups(): Promise<[Faculty[], Department[], StudyProgram[]]> {
    lookups ??= Promise.all([
        api.get<Collection<Faculty>>('faculties').then((r) => r.data.data),
        api.get<Collection<Department>>('departments').then((r) => r.data.data),
        api.get<Collection<StudyProgram>>('study-programs').then((r) => r.data.data),
    ]).catch((error) => {
        lookups = null;
        throw error;
    });

    return lookups;
}

function matches(item: { code: string; name: string }, q: string): boolean {
    return item.code.toLowerCase().includes(q) || item.name.toLowerCase().includes(q);
}

let timer: ReturnType<typeof setTimeout> | undefined;
let requestId = 0;

watch(query, (value) => {
    clearTimeout(timer);
    const q = value.trim();

    if (q.length < 2) {
        groups.value = [];
        loading.value = false;

        return;
    }

    loading.value = true;
    timer = setTimeout(() => void search(q), 250);
});

async function search(q: string): Promise<void> {
    const id = ++requestId;
    const needle = q.toLowerCase();

    try {
        const [[faculties, departments, programs], rooms, users] = await Promise.all([
            loadLookups(),
            api.get<Paginated<Room>>('rooms', { params: { q, per_page: MAX_PER_GROUP } }).then((r) => r.data.data),
            api.get<Paginated<User>>('users', { params: { q, per_page: MAX_PER_GROUP } }).then((r) => r.data.data),
        ]);

        if (id !== requestId) return;

        const page = (tab: string, code: string) => route('superadmin.faculty-program.index', { tab, q: code });

        groups.value = [
            {
                label: 'Fakultas',
                results: faculties.filter((f) => matches(f, needle)).slice(0, MAX_PER_GROUP)
                    .map((f) => ({ key: `f${f.id}`, title: f.name, subtitle: f.code, href: page('faculties', f.code) })),
            },
            {
                label: 'Jurusan',
                results: departments.filter((d) => matches(d, needle)).slice(0, MAX_PER_GROUP)
                    .map((d) => ({ key: `d${d.id}`, title: d.name, subtitle: `${d.code} • ${d.faculty.code}`, href: page('departments', d.code) })),
            },
            {
                label: 'Program Studi',
                results: programs.filter((p) => matches(p, needle)).slice(0, MAX_PER_GROUP)
                    .map((p) => ({ key: `p${p.id}`, title: p.name, subtitle: `${p.code} • ${p.faculty.code}`, href: page('study-programs', p.code) })),
            },
            {
                label: 'Ruangan',
                results: rooms.map((r) => ({
                    key: `r${r.id}`,
                    title: r.name ?? `Ruang ${r.code}`,
                    subtitle: [r.code, r.building, r.faculty?.code ?? 'Bersama'].filter(Boolean).join(' • '),
                    href: route('superadmin.rooms.index', { q: r.code }),
                })),
            },
            {
                label: 'Akun',
                results: users.map((u) => ({
                    key: `u${u.id}`,
                    title: u.name,
                    subtitle: [u.email, u.role ? ROLE_LABELS[u.role] : null].filter(Boolean).join(' • '),
                    href: route('superadmin.accounts.index', { q: u.email }),
                })),
            },
        ].filter((group) => group.results.length > 0);
    } catch {
        if (id === requestId) groups.value = [];
    } finally {
        if (id === requestId) loading.value = false;
    }
}

const firstResult = computed(() => groups.value[0]?.results[0] ?? null);

function go(result: Result | null): void {
    if (!result) return;

    open.value = false;
    router.visit(result.href);
}

function close(event: MouseEvent): void {
    if (root.value && !root.value.contains(event.target as Node)) {
        open.value = false;
    }
}

onMounted(() => document.addEventListener('mousedown', close));
onBeforeUnmount(() => document.removeEventListener('mousedown', close));
</script>

<template>
    <div ref="root" class="w-full max-w-sm relative">
        <span class="absolute inset-y-0 left-0 pl-3.5 flex items-center pointer-events-none text-gray-400">
            <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                <path d="M21 21l-5.197-5.197m0 0A7.5 7.5 0 105.196 5.196a7.5 7.5 0 0010.607 10.607z" stroke-linecap="round" stroke-linejoin="round"/>
            </svg>
        </span>
        <input
            v-model="query"
            class="w-full pl-10 pr-4 py-2.5 bg-gray-50 rounded-xl text-xs placeholder:text-gray-400 focus:bg-white focus:outline-none focus:ring-2 focus:ring-primary/30 transition-all border border-gray-100"
            placeholder="Cari fakultas, jurusan, prodi, ruangan, akun..."
            type="search"
            aria-label="Pencarian global"
            @focus="open = true"
            @input="open = true"
            @keydown.enter.prevent="go(firstResult)"
            @keydown.esc="open = false"
        >

        <div
            v-if="open && query.trim().length >= 2"
            class="absolute left-0 right-0 top-full mt-2 max-h-[70vh] overflow-y-auto rounded-xl border border-gray-100 bg-white shadow-lg z-50"
        >
            <p v-if="loading && groups.length === 0" class="px-4 py-3 text-xs text-gray-400">Mencari...</p>
            <p v-else-if="groups.length === 0" class="px-4 py-3 text-xs text-gray-500">Tidak ada hasil untuk "{{ query.trim() }}".</p>
            <div v-for="group in groups" :key="group.label" class="py-1.5 border-b border-gray-50 last:border-b-0">
                <p class="px-4 pt-1 pb-1.5 text-[10px] font-bold uppercase tracking-wider text-gray-400">{{ group.label }}</p>
                <Link
                    v-for="result in group.results"
                    :key="result.key"
                    :href="result.href"
                    class="block px-4 py-2 hover:bg-gray-50 focus:bg-gray-50 focus:outline-none"
                    @click="open = false"
                >
                    <p class="text-xs font-semibold text-gray-900 truncate">{{ result.title }}</p>
                    <p class="text-[11px] text-gray-400 truncate">{{ result.subtitle }}</p>
                </Link>
            </div>
        </div>
    </div>
</template>
