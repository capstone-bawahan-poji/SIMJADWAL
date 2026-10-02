<script setup lang="ts">
import { computed } from 'vue';
import AppLogo from '@/Components/shared/AppLogo.vue';
import GlobalSearch from '@/Components/shared/GlobalSearch.vue';
import { Link, usePage } from '@inertiajs/vue3';
import { initials, ROLE_LABELS } from '@/lib/labels';

const page = usePage();
const user = computed(() => page.props.auth.user);
const userName = computed(() => user.value?.name ?? '');
const userInitials = computed(() => initials(userName.value));
const roleLabel = computed(() => (user.value?.role ? ROLE_LABELS[user.value.role] : ''));
const isSuperAdmin = computed(() => user.value?.role === 'superadmin');

const allNavItems = [
    {
        label: 'Dashboard',
        href: route('dashboard'),
        routeName: 'dashboard',
        icon: `<path d="M3 13.125C3 12.504 3.504 12 4.125 12h2.25c.621 0 1.125.504 1.125 1.125v6.75C7.5 20.496 6.996 21 6.375 21h-2.25A1.125 1.125 0 013 19.875v-6.75zM9.75 8.625c0-.621.504-1.125 1.125-1.125h2.25c.621 0 1.125.504 1.125 1.125v11.25c0 .621-.504 1.125-1.125 1.125h-2.25a1.125 1.125 0 01-1.125-1.125V8.625zM16.5 4.125c0-.621.504-1.125 1.125-1.125h2.25C20.496 3 21 3.504 21 4.125v15.75c0 .621-.504 1.125-1.125 1.125h-2.25a1.125 1.125 0 01-1.125-1.125V4.125z" stroke-linecap="round" stroke-linejoin="round"/>`,
    },
    {
        label: 'Kelola Akun & Role',
        href: route('superadmin.accounts.index'),
        routeName: 'superadmin.accounts.index',
        icon: `<path d="M15 19.128a9.38 9.38 0 002.625.372 9.337 9.337 0 004.121-.952 4.125 4.125 0 00-7.533-2.493M15 19.128v-.003c0-1.113-.285-2.16-.786-3.07M15 19.128v.106A12.318 12.318 0 018.624 21c-2.331 0-4.512-.645-6.374-1.766l-.001-.109a6.375 6.375 0 0111.964-3.07M12 6.375a3.375 3.375 0 11-6.75 0 3.375 3.375 0 016.75 0zm8.25 2.25a2.625 2.625 0 11-5.25 0 2.625 2.625 0 015.25 0z" stroke-linecap="round" stroke-linejoin="round"/>`,
    },
    {
        label: 'Data Fakultas & Prodi',
        href: route('superadmin.faculty-program.index'),
        routeName: 'superadmin.faculty-program.index',
        icon: `<path d="M12 21v-8.25M15.75 21v-8.25M8.25 21v-8.25M3 9l9-6 9 6m-1.5 12V10.5h-15V21" stroke-linecap="round" stroke-linejoin="round"/>`,
    },
    {
        label: 'Data Ruangan',
        href: route('superadmin.rooms.index'),
        routeName: 'superadmin.rooms.index',
        icon: `<path d="M3.75 21h16.5M4.5 3h15M5.25 3v18m13.5-18v18M9 6.75h1.5m-1.5 3h1.5m-1.5 3h1.5m3-6H15m-1.5 3H15m-1.5 3H15M9 21v-3.375c0-.621.504-1.125 1.125-1.125h3.75c.621 0 1.125.504 1.125 1.125V21" stroke-linecap="round" stroke-linejoin="round"/>`,
    },
    {
        label: 'Data Jadwal',
        href: route('superadmin.slots.index'),
        routeName: 'superadmin.slots.index',
        icon: `<path d="M12 6v6h4.5m4.5 0a9 9 0 11-18 0 9 9 0 0118 0z" stroke-linecap="round" stroke-linejoin="round"/>`,
    },
];

const profileIcon = `<path d="M15.75 6a3.75 3.75 0 11-7.5 0 3.75 3.75 0 017.5 0zM4.501 20.118a7.5 7.5 0 0114.998 0A17.933 17.933 0 0112 21.75c-2.676 0-5.216-.584-7.499-1.632z" stroke-linecap="round" stroke-linejoin="round"/>`;

const navItems = computed(() => [
    ...allNavItems.filter((item) => isSuperAdmin.value || item.routeName === 'dashboard'),
    { label: 'Profil Saya', href: route('profile.edit'), routeName: 'profile.edit', icon: profileIcon },
]);

function isActive(routeName: string) {
    try {
        return route().current(routeName);
    } catch {
        return false;
    }
}
</script>

<template>
    <div class="min-h-screen bg-gray-50 font-sans">
        <!-- Sidebar -->
        <aside class="fixed left-0 top-0 h-full w-72 bg-white z-50 flex flex-col shadow-sm border-r border-gray-100">
            <!-- Logo -->
            <div class="h-20 flex items-center px-6 gap-3.5 border-b border-gray-100 shrink-0">
                <div class="w-10 h-10 rounded-xl overflow-hidden flex items-center justify-center shadow-sm shrink-0 bg-primary/5">
                    <AppLogo variant="mark" class="h-8 w-auto" />
                </div>
                <div class="min-w-0">
                    <h1 class="text-sm font-bold text-gray-900 tracking-tight leading-tight truncate">Sistem Penjadwalan Mata Kuliah</h1>
                    <p class="text-xs font-medium text-gray-400 mt-0.5">{{ isSuperAdmin ? 'Superadmin Portal' : roleLabel }}</p>
                </div>
            </div>

            <!-- Navigation -->
            <nav class="flex-1 p-4 space-y-1 overflow-y-auto">
                <template v-for="item in navItems" :key="item.label">
                    <Link
                        :href="item.href"
                        :class="[
                            'flex items-center gap-3.5 px-4 py-3 text-sm font-semibold rounded-xl transition-colors',
                            isActive(item.routeName)
                                ? 'text-primary bg-primary/8 shadow-xs'
                                : 'text-gray-500 hover:text-primary hover:bg-gray-50',
                        ]"
                    >
                        <svg
                            class="w-5 h-5 shrink-0"
                            :class="isActive(item.routeName) ? 'text-primary' : 'text-gray-400'"
                            fill="none"
                            stroke="currentColor"
                            :stroke-width="isActive(item.routeName) ? 2 : 1.8"
                            viewBox="0 0 24 24"
                            v-html="item.icon"
                        />
                        {{ item.label }}
                    </Link>
                </template>
            </nav>

            <!-- User Profile Footer -->
            <div class="border-t border-gray-100 p-4 shrink-0">
                <div class="flex items-center gap-3 px-2">
                    <div class="relative shrink-0">
                        <div class="w-9 h-9 rounded-full bg-primary text-white flex items-center justify-center font-bold text-xs">
                            {{ userInitials }}
                        </div>
                        <span class="absolute bottom-0 right-0 w-2.5 h-2.5 bg-emerald-500 rounded-full border-2 border-white"></span>
                    </div>
                    <div class="min-w-0 flex-1">
                        <p class="text-xs font-bold text-gray-900 truncate">{{ userName }}</p>
                        <p class="text-[11px] text-gray-400 truncate">{{ roleLabel }}</p>
                    </div>
                    <Link
                        :href="route('logout')"
                        method="post"
                        as="button"
                        class="p-1.5 rounded-lg text-gray-400 hover:text-red-500 hover:bg-red-50 transition-colors shrink-0"
                        title="Keluar"
                    >
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                            <path d="M15.75 9V5.25A2.25 2.25 0 0013.5 3h-6a2.25 2.25 0 00-2.25 2.25v13.5A2.25 2.25 0 007.5 21h6a2.25 2.25 0 002.25-2.25V15m3 0l3-3m0 0l-3-3m3 3H9" stroke-linecap="round" stroke-linejoin="round"/>
                        </svg>
                    </Link>
                </div>
            </div>
        </aside>

        <!-- Main content area -->
        <div class="pl-72 min-h-screen flex flex-col">
            <!-- Topbar -->
            <header class="fixed top-0 left-72 right-0 h-16 bg-white/80 backdrop-blur-xl border-b border-gray-100 z-40 flex items-center justify-between px-6">
                <!-- Search: superadmin only, the results open superadmin pages -->
                <GlobalSearch v-if="isSuperAdmin" />
                <div v-else />

                <!-- Right side -->
                <div class="flex items-center gap-4 shrink-0">
                    <div class="text-right hidden sm:block">
                        <p class="text-xs font-bold text-gray-900 leading-tight">{{ userName }}</p>
                        <p class="text-[11px] font-medium text-gray-400 leading-tight">{{ roleLabel }}</p>
                    </div>
                    <div class="relative">
                        <div class="w-10 h-10 rounded-full bg-primary text-white flex items-center justify-center font-bold text-xs shadow-sm">
                            {{ userInitials }}
                        </div>
                        <span class="absolute bottom-0 right-0 w-2.5 h-2.5 bg-emerald-500 rounded-full border-2 border-white"></span>
                    </div>
                </div>
            </header>

            <!-- Page Content -->
            <main class="flex-1 mt-16 bg-gray-50 min-h-[calc(100vh-4rem)]">
                <slot />
            </main>
        </div>
    </div>
</template>
