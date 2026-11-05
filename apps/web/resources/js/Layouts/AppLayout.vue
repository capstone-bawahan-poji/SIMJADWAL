<script setup lang="ts">
import { computed, ref, watch } from 'vue';
import AppLogo from '@/Components/shared/AppLogo.vue';
import GlobalSearch from '@/Components/shared/GlobalSearch.vue';
import { Link, usePage } from '@inertiajs/vue3';
import { initials, ROLE_LABELS } from '@/lib/labels';
import { navFor, PORTAL_LABELS, PROFILE_ICON } from '@/lib/navigation';

const page = usePage();
const user = computed(() => page.props.auth.user);
const userName = computed(() => user.value?.name ?? '');
const userInitials = computed(() => initials(userName.value));
const roleLabel = computed(() => (user.value?.role ? ROLE_LABELS[user.value.role] : ''));
const isSuperAdmin = computed(() => user.value?.role === 'superadmin');
const sidebarOpen = ref(false);

const navItems = computed(() => [
    ...navFor(user.value?.role).map((item) => ({ ...item, href: item.href ?? route(item.routeName) })),
    { label: 'Profil Saya', href: route('profile.edit'), routeName: 'profile.edit', icon: PROFILE_ICON },
]);

watch(() => page.url, () => {
    sidebarOpen.value = false;
});

function isActive(routeName: string, href?: string) {
    try {
        if (!route().current(routeName)) return false;
        if (!href?.includes('?')) return true;

        const target = new URL(href, window.location.origin);
        const current = new URL(page.url, window.location.origin);

        return target.search === current.search;
    } catch {
        return false;
    }
}
</script>

<template>
    <div class="min-h-screen bg-gray-50 font-sans" @keydown.esc.window="sidebarOpen = false">
        <button
            v-if="sidebarOpen"
            type="button"
            class="fixed inset-0 z-40 bg-gray-900/40 lg:hidden"
            aria-label="Tutup menu"
            @click="sidebarOpen = false"
        />

        <aside
            id="main-navigation"
            :class="[
                'fixed left-0 top-0 z-50 flex h-full w-64 flex-col border-r border-gray-200 bg-white transition-transform lg:translate-x-0',
                sidebarOpen ? 'translate-x-0' : '-translate-x-full',
            ]"
        >
            <div class="flex h-16 shrink-0 items-center gap-3 border-b border-gray-200 px-5">
                <AppLogo variant="mark" class="h-8 w-auto shrink-0" />
                <div class="min-w-0">
                    <p class="truncate text-sm font-semibold text-gray-900">Sistem Penjadwalan</p>
                    <p class="truncate text-xs text-gray-500">{{ (user?.role && PORTAL_LABELS[user.role]) || roleLabel }}</p>
                </div>
            </div>

            <nav class="flex-1 space-y-0.5 overflow-y-auto p-3" aria-label="Menu utama">
                <Link
                    v-for="item in navItems"
                    :key="item.label"
                    :href="item.href"
                    :aria-current="isActive(item.routeName, item.href) ? 'page' : undefined"
                    :class="[
                        'flex items-center gap-3 rounded-lg px-3 py-2 text-sm transition-colors',
                        isActive(item.routeName, item.href)
                            ? 'bg-gray-100 font-semibold text-gray-900'
                            : 'font-medium text-gray-600 hover:bg-gray-50 hover:text-gray-900',
                    ]"
                    @click="sidebarOpen = false"
                >
                    <svg
                        class="h-5 w-5 shrink-0"
                        :class="isActive(item.routeName) ? 'text-primary' : 'text-gray-400'"
                        fill="none"
                        stroke="currentColor"
                        stroke-width="1.8"
                        viewBox="0 0 24 24"
                        aria-hidden="true"
                        v-html="item.icon"
                    />
                    {{ item.label }}
                </Link>
            </nav>

            <div class="flex shrink-0 items-center gap-3 border-t border-gray-200 p-4">
                <div class="flex h-9 w-9 shrink-0 items-center justify-center rounded-full bg-gray-100 text-xs font-semibold text-gray-700">
                    {{ userInitials }}
                </div>
                <div class="min-w-0 flex-1">
                    <p class="truncate text-sm font-medium text-gray-900">{{ userName }}</p>
                    <p class="truncate text-xs text-gray-500">{{ roleLabel }}</p>
                </div>
                <Link
                    :href="route('logout')"
                    method="post"
                    as="button"
                    class="shrink-0 rounded-lg p-2 text-gray-400 transition-colors hover:bg-gray-100 hover:text-gray-900"
                    title="Keluar"
                    aria-label="Keluar"
                >
                    <svg class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" aria-hidden="true">
                        <path d="M15.75 9V5.25A2.25 2.25 0 0013.5 3h-6a2.25 2.25 0 00-2.25 2.25v13.5A2.25 2.25 0 007.5 21h6a2.25 2.25 0 002.25-2.25V15m3 0l3-3m0 0l-3-3m3 3H9" stroke-linecap="round" stroke-linejoin="round" />
                    </svg>
                </Link>
            </div>
        </aside>

        <div class="flex min-h-screen flex-col lg:pl-64">
            <header class="sticky top-0 z-30 flex h-16 items-center gap-3 border-b border-gray-200 bg-white px-4 md:px-6">
                <button
                    type="button"
                    class="rounded-lg p-2 text-gray-600 hover:bg-gray-100 lg:hidden"
                    aria-label="Buka menu"
                    aria-controls="main-navigation"
                    :aria-expanded="sidebarOpen"
                    @click="sidebarOpen = true"
                >
                    <svg class="h-5 w-5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" aria-hidden="true">
                        <path d="M3.75 6.75h16.5M3.75 12h16.5m-16.5 5.25h16.5" stroke-linecap="round" stroke-linejoin="round" />
                    </svg>
                </button>
                <GlobalSearch v-if="isSuperAdmin" />
            </header>

            <main class="flex-1">
                <slot />
            </main>
        </div>
    </div>
</template>
