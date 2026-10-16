<script setup lang="ts">
import { ref } from 'vue';
import AppLogo from './shared/AppLogo.vue';
import ConfirmDialog from './ui/ConfirmDialog.vue';

const props = defineProps<{
  activeView: string;
  adminName: string;
  adminRole: string;
}>();
const emit = defineEmits<{
  'update:activeView': [view: string];
  logout: [];
}>();

// Sidebar nav items
const navItems = [
  { key: 'config', label: 'Konfigurasi GA' },
  { key: 'data', label: 'Data & Constraint' },
  { key: 'run', label: 'Run' },
  { key: 'sync', label: 'Sinkronisasi' },
];

const showLogoutDialog = ref(false);

function confirmLogout() {
  showLogoutDialog.value = false;
  emit('logout');
}

function isActive(key: string) {
  return props.activeView === key;
}
</script>

<template>
  <aside class="w-72 flex-shrink-0 h-full bg-white border-r border-gray-100 flex flex-col z-30">

    <div class="min-h-20 flex items-center gap-3 px-6 border-b border-gray-100 shrink-0">
      <div class="flex h-10 w-10 shrink-0 items-center justify-center overflow-hidden rounded-xl bg-primary/5">
        <AppLogo variant="mark" class="h-8 w-auto" />
      </div>
      <div class="min-w-0">
        <h1 class="text-sm font-bold text-gray-900 tracking-tight leading-tight">
          Sistem Penjadwalan Mata Kuliah
        </h1>
        <p class="text-xs font-medium text-gray-400 mt-1">{{ props.adminRole }}</p>
      </div>
    </div>

    <nav class="flex-1 p-4 space-y-1 overflow-y-auto">
      <button
        v-for="item in navItems"
        :key="item.key"
        type="button"
        class="w-full px-4 py-3 text-sm font-semibold rounded-lg transition-colors text-left"
        :class="[
          isActive(item.key)
            ? 'text-primary bg-primary/[0.08]'
            : 'text-gray-500 hover:text-primary hover:bg-gray-50',
        ]"
        @click="$emit('update:activeView', item.key)"
      >
        {{ item.label }}
      </button>
    </nav>

    <div class="border-t border-gray-100 p-4 shrink-0">
      <div class="flex items-center gap-3 px-2">
        <div class="min-w-0 flex-1">
          <p class="text-xs font-bold text-gray-900 truncate">{{ props.adminName }}</p>
          <p class="text-[11px] text-gray-400 truncate">{{ props.adminRole }}</p>
        </div>

        <button
          type="button"
          class="px-2 py-1.5 rounded-lg text-xs font-semibold text-gray-500 hover:text-red-600 hover:bg-red-50 transition-colors shrink-0"
          @click="showLogoutDialog = true"
        >Keluar</button>
      </div>
    </div>
  </aside>

  <!-- Logout confirm dialog -->
  <ConfirmDialog
    :show="showLogoutDialog"
    title="Keluar dari Sistem?"
    confirm-label="Keluar"
    :danger="true"
    @confirm="confirmLogout"
    @close="showLogoutDialog = false"
  >
    Sesi Anda akan diakhiri. Pastikan semua perubahan sudah tersimpan sebelum keluar.
  </ConfirmDialog>
</template>
