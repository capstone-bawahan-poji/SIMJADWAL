<script setup lang="ts">
import { computed } from 'vue';
import { useRoute, useRouter } from 'vue-router';
import { useAuth } from '@/composables/useAuth';
import { useDataset } from '@/composables/useDataset';
import { useGaConfig } from '@/composables/useGaConfig';
import AppSidebar from '@/components/layout/AppSidebar.vue';
import Alert from '@/components/ui/Alert.vue';
import Button from '@/components/ui/Button.vue';

const route = useRoute();
const router = useRouter();
const { user, logout } = useAuth();
const { clear: clearDataset, stale: datasetStale, loading: datasetLoading, load: reloadDataset } = useDataset();
const { resetDefault } = useGaConfig();

const roleLabels: Record<string, string> = {
  superadmin: 'Super Admin', admin_fakultas: 'Admin Fakultas', admin_tpb: 'Admin TPB',
  admin_prodi: 'Admin Prodi', dosen: 'Dosen', mahasiswa: 'Mahasiswa',
};
const roleLabel = computed(() => roleLabels[user.value?.role ?? ''] ?? '');

async function onLogout() {
  await logout();
  clearDataset();
  resetDefault(null);
  router.push({ name: 'login' });
}

const activeView = computed(() => String(route.name ?? ''));
</script>

<template>
  <div class="flex h-screen w-screen overflow-hidden bg-gray-50">
    <AppSidebar
      :active-view="activeView"
      :admin-name="user?.name ?? ''"
      :admin-role="roleLabel"
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
          <p class="text-xs font-bold text-gray-900 leading-tight">{{ user?.name }}</p>
          <p class="text-[11px] font-medium text-gray-400 leading-tight">{{ roleLabel }}</p>
        </div>
      </header>

      <!-- Page content -->
      <main class="flex-1 overflow-y-auto bg-gray-50">
        <div v-if="datasetStale" class="px-6 pt-4">
          <Alert variant="warning" title="Data di server berubah">
            <div class="flex flex-wrap items-center justify-between gap-3">
              <span>Data yang dimuat sudah tidak sama dengan server. Muat ulang sebelum menjalankan GA agar hasilnya bisa diunggah.</span>
              <Button variant="ghost" :loading="datasetLoading" @click="reloadDataset()">Muat ulang data</Button>
            </div>
          </Alert>
        </div>
        <RouterView />
      </main>
    </div>
  </div>
</template>
