<script setup lang="ts">
import { computed, ref, watch } from 'vue';
import AppLogo from '@/Components/shared/AppLogo.vue';
import PageContainer from '@/Components/shared/PageContainer.vue';
import GlobalSearch from '@/Components/shared/GlobalSearch.vue';
import { Link, usePage } from '@inertiajs/vue3';
import { initials, ROLE_LABELS } from '@/lib/labels';
import { DOWNLOAD_APP_ITEM, navFor, type NavItem, PORTAL_LABELS } from '@/lib/navigation';

/** bare: the page paints its own full-width background and places PageContainer itself. */
defineProps<{ bare?: boolean }>();

const page = usePage();
const user = computed(() => page.props.auth.user);
const userName = computed(() => user.value?.name ?? '');
const userInitials = computed(() => initials(userName.value));
const roleLabel = computed(() => (user.value?.role ? ROLE_LABELS[user.value.role] : ''));
const isSuperAdmin = computed(() => user.value?.role === 'superadmin');
const sidebarOpen = ref(false);
const accountMenuOpen = ref(false);

const navItems = computed<NavItem[]>(() => [
    ...navFor(user.value?.role)
        .filter((item) => item !== DOWNLOAD_APP_ITEM)
        .map((item) => (item.external ? item : { ...item, href: item.href ?? route(item.routeName) })),
]);
const showDownloadApp = computed(() => navFor(user.value?.role).includes(DOWNLOAD_APP_ITEM));

watch(() => page.url, () => {
    sidebarOpen.value = false;
    accountMenuOpen.value = false;
});

function closeMenus(): void {
    sidebarOpen.value = false;
    accountMenuOpen.value = false;
}

function isActive(item: NavItem) {
    if (item.external) return false;

    const { routeName, href } = item;

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
    <div class="min-h-screen bg-gray-50 font-sans" @keydown.esc.window="closeMenus">
        <button
            v-if="sidebarOpen"
            type="button"
            class="fixed inset-0 z-40 bg-gray-900/40 lg:hidden"
            aria-label="Tutup menu"
            @click="sidebarOpen = false"
        />
        <button
            v-if="accountMenuOpen"
            type="button"
            class="fixed inset-0 z-40 cursor-default"
            aria-label="Tutup menu akun"
            @click="accountMenuOpen = false"
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
                <component
                    :is="item.external ? 'a' : Link"
                    v-for="item in navItems"
                    :key="item.label"
                    :href="item.href"
                    v-bind="item.external ? { target: '_blank', rel: 'noopener noreferrer' } : {}"
                    :aria-current="isActive(item) ? 'page' : undefined"
                    :class="[
                        'flex items-center gap-3 rounded-lg px-3 py-2 text-sm transition-colors',
                        isActive(item)
                            ? 'bg-gray-100 font-semibold text-gray-900'
                            : 'font-medium text-gray-600 hover:bg-gray-50 hover:text-gray-900',
                    ]"
                    @click="sidebarOpen = false"
                >
                    <svg
                        class="h-5 w-5 shrink-0"
                        :class="isActive(item) ? 'text-primary' : 'text-gray-400'"
                        fill="none"
                        stroke="currentColor"
                        stroke-width="1.8"
                        viewBox="0 0 24 24"
                        aria-hidden="true"
                        v-html="item.icon"
                    />
                    {{ item.label }}
                </component>
            </nav>

            <div v-if="showDownloadApp" class="shrink-0 px-3 pb-3">
                <a
                    :href="DOWNLOAD_APP_ITEM.href"
                    target="_blank"
                    rel="noopener noreferrer"
                    class="flex w-full items-center justify-center gap-2 rounded-lg border border-primary px-3 py-2.5 text-sm font-semibold text-primary transition-colors hover:bg-blue-50 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-primary focus-visible:ring-offset-2"
                    @click="sidebarOpen = false"
                >
                    <svg
                        class="h-4 w-4 shrink-0"
                        fill="none"
                        stroke="currentColor"
                        stroke-width="1.8"
                        viewBox="0 0 24 24"
                        aria-hidden="true"
                        v-html="DOWNLOAD_APP_ITEM.icon"
                    />
                    {{ DOWNLOAD_APP_ITEM.label }}
                </a>
            </div>

            <div class="relative shrink-0 border-t border-gray-200 p-3">
                <button
                    type="button"
                    class="flex w-full items-center gap-3 rounded-lg p-2 text-left transition-colors hover:bg-gray-50 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-primary"
                    aria-haspopup="menu"
                    aria-controls="account-menu"
                    :aria-expanded="accountMenuOpen"
                    @click="accountMenuOpen = !accountMenuOpen"
                >
                    <span class="flex h-9 w-9 shrink-0 items-center justify-center rounded-full bg-gray-100 text-xs font-semibold text-gray-700">
                        {{ userInitials }}
                    </span>
                    <span class="min-w-0 flex-1">
                        <span class="block truncate text-sm font-medium text-gray-900">{{ userName }}</span>
                        <span class="block truncate text-xs text-gray-500">{{ roleLabel }}</span>
                    </span>
                    <svg
                        class="h-4 w-4 shrink-0 text-gray-400 transition-transform"
                        :class="{ 'rotate-180': accountMenuOpen }"
                        fill="none"
                        stroke="currentColor"
                        stroke-width="2"
                        viewBox="0 0 24 24"
                        aria-hidden="true"
                    >
                        <path d="m6 9 6 6 6-6" stroke-linecap="round" stroke-linejoin="round" />
                    </svg>
                </button>

                <div
                    v-if="accountMenuOpen"
                    id="account-menu"
                    role="menu"
                    class="absolute bottom-full left-3 right-3 z-[60] mb-2 overflow-hidden rounded-xl border border-gray-200 bg-white p-1.5 shadow-lg"
                >
                    <Link
                        :href="route('profile.edit')"
                        role="menuitem"
                        class="flex items-center gap-3 rounded-lg px-3 py-2.5 text-sm font-medium text-gray-700 transition-colors hover:bg-gray-50 hover:text-gray-900 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-primary"
                        @click="closeMenus"
                    >
                        <svg class="h-5 w-5 text-gray-400" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24" aria-hidden="true">
                            <path d="M15.75 6a3.75 3.75 0 1 1-7.5 0 3.75 3.75 0 0 1 7.5 0ZM4.5 20.1a7.5 7.5 0 0 1 15 0A17.9 17.9 0 0 1 12 21.75 17.9 17.9 0 0 1 4.5 20.1Z" stroke-linecap="round" stroke-linejoin="round" />
                        </svg>
                        Profil Saya
                    </Link>
                    <Link
                        :href="route('logout')"
                        method="post"
                        as="button"
                        role="menuitem"
                        class="flex w-full items-center gap-3 rounded-lg px-3 py-2.5 text-left text-sm font-medium text-gray-700 transition-colors hover:bg-red-50 hover:text-red-700 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-primary"
                        @click="closeMenus"
                    >
                        <svg class="h-5 w-5 text-gray-400" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24" aria-hidden="true">
                            <path d="M15.75 9V5.25A2.25 2.25 0 0 0 13.5 3h-6a2.25 2.25 0 0 0-2.25 2.25v13.5A2.25 2.25 0 0 0 7.5 21h6a2.25 2.25 0 0 0 2.25-2.25V15m3 0 3-3m0 0-3-3m3 3H9" stroke-linecap="round" stroke-linejoin="round" />
                        </svg>
                        Keluar
                    </Link>
                </div>
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
                <slot v-if="bare" />
                <PageContainer v-else>
                    <slot />
                </PageContainer>
            </main>
        </div>
    </div>
</template>
