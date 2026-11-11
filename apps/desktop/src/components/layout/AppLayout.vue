<script setup lang="ts">
import { computed, onMounted } from 'vue';
import { useRoute, useRouter } from 'vue-router';
import { useAuth } from '@/composables/useAuth';
import { useDataset } from '@/composables/useDataset';
import { useGaConfig } from '@/composables/useGaConfig';
import { useGaRunner } from '@/composables/useGaRunner';
import { useRunSync } from '@/composables/useRunSync';
import AppSidebar from '@/components/layout/AppSidebar.vue';
import ContextBar from '@/components/layout/ContextBar.vue';
import Alert from '@/components/ui/Alert.vue';
import Button from '@/components/ui/Button.vue';

const route = useRoute();
const router = useRouter();
const { user, logout } = useAuth();
const { dataset, studyProgramId, clear: clearDataset, stale: datasetStale, loading: datasetLoading, load: reloadDataset } = useDataset();
const { resetDefault } = useGaConfig();
const { running } = useGaRunner();
const { items: runItems, load: loadRuns } = useRunSync();

const roleLabels: Record<string, string> = {
  superadmin: 'Super admin', admin_fakultas: 'Admin fakultas', admin_tpb: 'Admin TPB',
  admin_prodi: 'Admin program studi', dosen: 'Dosen', mahasiswa: 'Mahasiswa',
};
const roleLabel = computed(() => roleLabels[user.value?.role ?? ''] ?? '');
const isSuperAdmin = computed(() => user.value?.role === 'superadmin');
const unsentCount = computed(() => runItems.value.filter((i) => i.upload.status !== 'uploaded' && !i.cancelled).length);

onMounted(() => {
  void loadRuns();
  if (!dataset.value && !datasetLoading.value && (!isSuperAdmin.value || studyProgramId.value)) void reloadDataset();
});

async function onLogout() {
  await logout();
  clearDataset();
  resetDefault(null);
  router.push({ name: 'login' });
}

const activeView = computed(() => String(route.name ?? ''));
</script>

<template>
  <div class="flex h-screen w-screen overflow-hidden bg-canvas">
    <AppSidebar
      :active-view="activeView"
      :admin-name="user?.name ?? ''"
      :admin-role="roleLabel"
      :super-admin="isSuperAdmin"
      :running="running"
      :unsent-count="unsentCount"
      @update:active-view="router.push({ name: $event })"
      @logout="onLogout"
    />

    <div class="flex min-w-0 flex-1 flex-col overflow-hidden">
      <ContextBar
        :super-admin="isSuperAdmin"
        :scope-name="user?.study_program ? `${user.study_program.code} ${user.study_program.name}` : user?.role === 'admin_tpb' ? 'TPB (tingkat institut)' : null"
        :workflow="!route.meta.superadmin"
      />

      <main class="flex-1 overflow-y-auto">
        <div v-if="datasetStale && !route.meta.superadmin" class="px-8 pt-8">
          <Alert variant="warning" title="Data di server telah berubah">
            <div class="flex flex-wrap items-center justify-between gap-4">
              <span>Data yang dimuat tidak lagi sesuai dengan data di server. Muat ulang data sebelum menjalankan GA agar hasilnya dapat dikirim.</span>
              <Button size="sm" :loading="datasetLoading" @click="reloadDataset()">Muat ulang data</Button>
            </div>
          </Alert>
        </div>
        <RouterView />
      </main>
    </div>
  </div>
</template>
