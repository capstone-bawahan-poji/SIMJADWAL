<script setup lang="ts">
import { computed } from 'vue';
import { useRoute, useRouter } from 'vue-router';
import { useAuth } from '@/composables/useAuth';
import AppSidebar from '@/components/layout/AppSidebar.vue';

const route = useRoute();
const router = useRouter();
const { logout } = useAuth();

const activeView = computed(() => String(route.name ?? ''));

function onLogout() {
  logout();
  router.push({ name: 'login' });
}
</script>

<template>
  <div class="flex h-screen w-screen overflow-hidden bg-gray-50">
    <AppSidebar
      :active-view="activeView"
      admin-name="Ahmad Rasyid"
      admin-role="Admin Fakultas"
      @update:active-view="router.push({ name: $event })"
      @logout="onLogout"
    />

    <!-- Right: topbar + content -->
    <div class="flex-1 flex flex-col overflow-hidden">
      <!-- Topbar -->
      <header class="h-16 bg-white/80 backdrop-blur-xl border-b border-gray-100 flex items-center justify-between px-6 shrink-0 z-40">
        <div class="text-sm font-semibold text-gray-900 capitalize">
          {{ route.meta.title }}
        </div>
        <div class="text-right">
          <p class="text-xs font-bold text-gray-900 leading-tight">Ahmad Rasyid</p>
          <p class="text-[11px] font-medium text-gray-400 leading-tight">Admin Fakultas</p>
        </div>
      </header>

      <!-- Page content -->
      <main class="flex-1 overflow-y-auto bg-gray-50">
        <RouterView />
      </main>
    </div>
  </div>
</template>
