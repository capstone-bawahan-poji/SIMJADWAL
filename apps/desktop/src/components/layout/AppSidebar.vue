<script setup lang="ts">
import { computed, ref } from 'vue';
import { History, LoaderCircle, LogOut } from 'lucide-vue-next';
import AppLogo from '@/components/shared/AppLogo.vue';
import Button from '@/components/ui/Button.vue';
import ConfirmDialog from '@/components/ui/ConfirmDialog.vue';

const props = defineProps<{
  activeView: string;
  adminName: string;
  adminRole: string;
  superAdmin: boolean;
  running: boolean;
  unsentCount: number;
}>();
const emit = defineEmits<{
  'update:activeView': [view: string];
  logout: [];
}>();

const steps = computed(() => [
  { key: 'data', label: 'Data dan constraint' },
  { key: 'config', label: 'Konfigurasi GA' },
  { key: 'run', label: 'Run penjadwalan' },
  { key: 'sync', label: 'Sinkronisasi' },
]);

const showLogoutDialog = ref(false);

function confirmLogout() {
  showLogoutDialog.value = false;
  emit('logout');
}

const isActive = (key: string) => props.activeView === key;

const itemClass = (key: string) => [
  'flex h-10 w-full items-center gap-3 rounded-control px-3 text-left text-sm font-semibold transition-colors focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-primary',
  isActive(key) ? 'bg-primary-soft text-primary' : 'text-ink-tertiary hover:bg-canvas hover:text-ink',
];
</script>

<template>
  <aside class="z-30 flex h-full w-64 shrink-0 flex-col border-r border-line bg-white">
    <div class="flex h-[72px] shrink-0 items-center gap-3 border-b border-line px-6">
      <AppLogo variant="mark" class="h-8" />
      <div class="min-w-0">
        <p class="font-serif text-base font-semibold text-ink">SIMJADWAL</p>
        <p class="truncate text-xs text-ink-muted">Sistem Penjadwalan Perkuliahan</p>
      </div>
    </div>

    <nav class="flex flex-1 flex-col gap-6 overflow-y-auto px-4 py-6" aria-label="Navigasi utama">
      <div class="flex flex-col gap-1">
        <p class="px-3 pb-2 text-xs font-semibold text-ink-muted">Tahapan penjadwalan</p>
        <button
          v-for="(step, index) in steps"
          :key="step.key"
          type="button"
          :class="itemClass(step.key)"
          :aria-current="isActive(step.key) ? 'page' : undefined"
          @click="$emit('update:activeView', step.key)"
        >
          <span
            class="tabular flex size-6 shrink-0 items-center justify-center rounded-full text-xs"
            :class="isActive(step.key) ? 'bg-primary text-white' : 'border border-line-strong text-ink-muted'"
          >{{ index + 1 }}</span>
          <span class="flex-1 truncate">{{ step.label }}</span>
          <LoaderCircle v-if="step.key === 'run' && running" class="size-4 animate-spin text-primary" aria-label="Run berjalan" />
          <span
            v-if="step.key === 'sync' && unsentCount > 0"
            class="tabular rounded-badge bg-warning-bg px-2 text-xs leading-6 text-warning-ink"
            :aria-label="`${unsentCount} run belum terkirim`"
          >{{ unsentCount }}</span>
        </button>
      </div>

      <div v-if="superAdmin" class="flex flex-col gap-1">
        <p class="px-3 pb-2 text-xs font-semibold text-ink-muted">Administrasi</p>
        <button
          type="button"
          :class="itemClass('admin-history')"
          :aria-current="isActive('admin-history') ? 'page' : undefined"
          @click="$emit('update:activeView', 'admin-history')"
        >
          <History class="size-4 shrink-0" aria-hidden="true" />
          <span class="truncate">Riwayat run fakultas</span>
        </button>
      </div>
    </nav>

    <div class="flex shrink-0 items-center gap-3 border-t border-line px-6 py-4">
      <div class="min-w-0 flex-1">
        <p class="truncate text-sm font-semibold text-ink">{{ adminName }}</p>
        <p class="truncate text-xs text-ink-muted">{{ adminRole }}</p>
      </div>
      <Button variant="tertiary" size="sm" aria-label="Keluar" title="Keluar" @click="showLogoutDialog = true">
        <LogOut class="size-4" aria-hidden="true" />
      </Button>
    </div>
  </aside>

  <ConfirmDialog
    :show="showLogoutDialog"
    title="Keluar dari aplikasi?"
    confirm-label="Keluar"
    @confirm="confirmLogout"
    @close="showLogoutDialog = false"
  >
    Sesi Anda akan diakhiri. Hasil run yang belum dikirim tetap tersimpan di perangkat ini.
  </ConfirmDialog>
</template>
